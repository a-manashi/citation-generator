<?php

declare(strict_types=1);

namespace App\Libraries\Citation;

use Config\Citation;

abstract class AbstractClient
{
    public function __construct(
        protected readonly CitationHttp $http,
        protected readonly Citation $config,
    ) {
    }

    /**
     * @param array<string, scalar|null> $params
     * @param list<string>               $headers
     *
     * @return array<string, mixed>|null
     */
    protected function getJson(string $url, array $params = [], array $headers = []): ?array
    {
        $response = $this->http->get($this->withParams($url, $params), $headers);
        if ($response['status'] === 404) {
            return null;
        }
        if ($response['status'] >= 400) {
            throw new CitationException(
                'The citation source returned an unexpected response. Please try again.',
                CitationException::BAD_RESPONSE
            );
        }
        $data = json_decode($response['body'], true);
        if (! is_array($data)) {
            throw new CitationException(
                'The citation source returned an unexpected response. Please try again.',
                CitationException::BAD_RESPONSE
            );
        }

        return $data;
    }

    /**
     * @param array<string, scalar|null> $params
     */
    protected function withParams(string $url, array $params): string
    {
        $filtered = [];
        foreach ($params as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $filtered[$key] = $value;
        }
        if ($filtered === []) {
            return $url;
        }
        $glue = str_contains($url, '?') ? '&' : '?';

        return $url . $glue . http_build_query($filtered);
    }

    protected function email(): string
    {
        return $this->config->contactEmail();
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    protected function dateParts(mixed $parts): array
    {
        if (! is_array($parts) || ! isset($parts[0]) || ! is_array($parts[0])) {
            return ['', '', ''];
        }
        $row = $parts[0];

        return [
            CitationRecord::year((string) ($row[0] ?? '')),
            isset($row[1]) ? (string) (int) $row[1] : '',
            isset($row[2]) ? (string) (int) $row[2] : '',
        ];
    }

    protected function firstString(mixed $value): string
    {
        if (is_string($value)) {
            return CitationRecord::clean($value, 500);
        }
        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_string($item) && trim($item) !== '') {
                    return CitationRecord::clean($item, 500);
                }
            }
        }

        return '';
    }
}
