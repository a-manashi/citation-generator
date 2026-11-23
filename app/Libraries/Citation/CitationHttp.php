<?php

declare(strict_types=1);

namespace App\Libraries\Citation;

use Config\Citation;

/**
 * HTTPS client limited to the citation provider host allowlist.
 */
class CitationHttp
{
    public function __construct(private readonly Citation $config)
    {
    }

    /**
     * @param list<string> $headers
     *
     * @return array{status: int, body: string}
     */
    public function get(string $url, array $headers = []): array
    {
        $current = $url;
        for ($hop = 0; $hop < 4; $hop++) {
            $this->assertAllowed($current);
            $result = $this->requestOnce($current, $headers);
            if (in_array($result['status'], [301, 302, 303, 307, 308], true)) {
                if ($result['location'] === '') {
                    throw new CitationException(
                        'The citation source returned an unexpected response. Please try again.',
                        CitationException::BAD_RESPONSE
                    );
                }
                $current = $this->resolveUrl($current, $result['location']);
                continue;
            }
            if ($result['status'] === 429) {
                throw new CitationException(
                    'The citation source is busy. Please try again in a moment.',
                    CitationException::RATE_LIMIT
                );
            }
            if ($result['status'] >= 500) {
                throw new CitationException(
                    'The citation source is not responding right now. Please try again in a moment.',
                    CitationException::UNAVAILABLE
                );
            }

            return ['status' => $result['status'], 'body' => $result['body']];
        }

        throw new CitationException(
            'The citation source returned an unexpected response. Please try again.',
            CitationException::BAD_RESPONSE
        );
    }

    private function assertAllowed(string $url): void
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            throw new CitationException(
                'The citation source returned an unexpected response. Please try again.',
                CitationException::SKIP
            );
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $port = (int) ($parts['port'] ?? 443);
        if ($scheme !== 'https' || $host === '' || $port !== 443 || isset($parts['user']) || isset($parts['pass'])) {
            throw new CitationException(
                'The citation source returned an unexpected response. Please try again.',
                CitationException::SKIP
            );
        }
        if (! in_array($host, $this->config->allowedHosts, true) || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            throw new CitationException(
                'The citation source returned an unexpected response. Please try again.',
                CitationException::SKIP
            );
        }
        if (! $this->hostResolvesToPublicAddress($host)) {
            throw new CitationException(
                'The citation source is not responding right now. Please try again in a moment.',
                CitationException::UNAVAILABLE
            );
        }
    }

    private function hostResolvesToPublicAddress(string $host): bool
    {
        $ips = @gethostbynamel($host);
        if (! is_array($ips) || $ips === []) {
            return false;
        }
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $headers
     *
     * @return array{status: int, body: string, location: string}
     */
    private function requestOnce(string $url, array $headers): array
    {
        if (! function_exists('curl_init')) {
            throw new CitationException(
                'The citation source is not responding right now. Please try again in a moment.',
                CitationException::UNAVAILABLE
            );
        }

        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $ips = gethostbynamel($host);
        $ip = is_array($ips) ? ($ips[0] ?? '') : '';
        $timeout = max(3, min(30, $this->config->timeoutSeconds));
        $maxBytes = max(100000, min(2000000, $this->config->maxResponseBytes));
        $body = '';
        $received = 0;
        $location = '';

        $ch = curl_init($url);
        if ($ch === false) {
            throw new CitationException(
                'The citation source is not responding right now. Please try again in a moment.',
                CitationException::UNAVAILABLE
            );
        }

        $requestHeaders = $headers;
        $hasAccept = false;
        foreach ($requestHeaders as $header) {
            if (stripos($header, 'Accept:') === 0) {
                $hasAccept = true;
            }
        }
        if (! $hasAccept) {
            $requestHeaders[] = 'Accept: application/json';
        }
        $email = $this->config->contactEmail();
        $requestHeaders[] = 'User-Agent: ' . $this->config->userAgent . ($email !== '' ? ' (mailto:' . $email . ')' : '');

        $options = [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_HTTPHEADER     => $requestHeaders,
            CURLOPT_ENCODING       => '',
            CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$location): int {
                if (stripos($header, 'Location:') === 0) {
                    $location = trim(substr($header, 9));
                }

                return strlen($header);
            },
            CURLOPT_WRITEFUNCTION  => static function ($curl, string $data) use (&$body, &$received, $maxBytes): int {
                $length = strlen($data);
                $received += $length;
                if ($received > $maxBytes) {
                    return 0;
                }
                $body .= $data;

                return $length;
            },
        ];
        if ($ip !== '') {
            $options[CURLOPT_RESOLVE] = [$host . ':443:' . $ip];
        }
        if (defined('CURLPROTO_HTTPS')) {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
            if (defined('CURLOPT_REDIR_PROTOCOLS')) {
                $options[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTPS;
            }
        }
        curl_setopt_array($ch, $options);

        $ok = curl_exec($ch);
        $errno = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($ok === false || $errno !== 0) {
            if ($received > $maxBytes || $errno === 23) {
                log_message('error', 'Citation request failed: response too large host=' . $host);
                throw new CitationException(
                    'The citation source returned an unexpected response. Please try again.',
                    CitationException::BAD_RESPONSE
                );
            }
            log_message('error', 'Citation request failed: network host=' . $host);
            throw new CitationException(
                'The citation source is not responding right now. Please try again in a moment.',
                CitationException::UNAVAILABLE
            );
        }

        return ['status' => $status, 'body' => $body, 'location' => $location];
    }

    private function resolveUrl(string $base, string $location): string
    {
        $location = trim($location);
        if (preg_match('~^https://~i', $location) === 1) {
            return $location;
        }
        if (str_starts_with($location, '//')) {
            return 'https:' . $location;
        }
        if (preg_match('~^[a-z][a-z0-9+\-.]*:~i', $location) === 1) {
            throw new CitationException(
                'The citation source returned an unexpected response. Please try again.',
                CitationException::SKIP
            );
        }
        $parts = parse_url($base);
        $origin = 'https://' . strtolower((string) ($parts['host'] ?? ''));
        if (str_starts_with($location, '/')) {
            return $origin . $location;
        }
        $path = (string) ($parts['path'] ?? '/');
        $slash = strrpos($path, '/');
        $dir = $slash === false ? '/' : substr($path, 0, $slash + 1);

        return $origin . $dir . $location;
    }
}
