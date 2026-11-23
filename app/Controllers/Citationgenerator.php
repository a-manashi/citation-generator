<?php

namespace App\Controllers;

use App\Libraries\Citation\CitationService;
use App\Libraries\Citation\FormatterFactory;
use CodeIgniter\Security\Exceptions\SecurityException;
use Config\Citation;

class Citationgenerator extends \CodeIgniter\Controller
{
    public function index()
    {
        helper('url');

        return view('citationgenerator/indexv2', ['styles' => FormatterFactory::styles()]);
    }

    public function lookup()
    {
        if (! $this->guard()) {
            return $this->response;
        }

        $service = new CitationService();
        $style = $this->style();
        $provider = trim($this->postString('provider'));
        $workId = trim($this->postString('work_id'));
        if ($provider !== '' || $workId !== '') {
            $result = $service->select($provider, $workId, $style);
        } else {
            $result = $service->lookup($this->postString('query'), $style);
        }

        return $this->respond($result, ! empty($result['ok']) ? 200 : 422);
    }

    public function search()
    {
        if (! $this->guard()) {
            return $this->response;
        }

        $service = new CitationService();
        $result = $service->search($this->postString('query'), $this->style());

        return $this->respond($result, ! empty($result['ok']) ? 200 : 422);
    }

    public function format()
    {
        if (! $this->guard()) {
            return $this->response;
        }

        $service = new CitationService();
        $result = $service->format([
            'title'        => $this->postString('title'),
            'authors_text' => $this->postString('authors_text'),
            'year'         => $this->postString('year'),
            'journal'      => $this->postString('journal'),
            'volume'       => $this->postString('volume'),
            'issue'        => $this->postString('issue'),
            'pages'        => $this->postString('pages'),
            'publisher'    => $this->postString('publisher'),
            'edition'      => $this->postString('edition'),
            'isbn'         => $this->postString('isbn'),
            'doi'          => $this->postString('doi'),
            'url'          => $this->postString('url'),
            'type'         => $this->postString('type'),
        ], $this->style());

        return $this->respond($result, ! empty($result['ok']) ? 200 : 422);
    }

    private function style(): string
    {
        $style = strtolower(trim($this->postString('style')));

        return $style !== '' ? $style : 'apa';
    }

    private function postString(string $name): string
    {
        $value = $this->request->getPost($name);

        return is_string($value) ? $value : '';
    }

    private function guard(): bool
    {
        try {
            service('security')->verify($this->request);
        } catch (SecurityException $e) {
            $this->respond([
                'ok'      => false,
                'mode'    => 'error',
                'message' => 'Your session token expired. Refresh the page and try again.',
            ], 403);

            return false;
        }

        $config = config(Citation::class);
        $allowed = service('throttler')->check(
            'citation' . md5((string) $this->request->getIPAddress()),
            $config->rateLimit,
            $config->rateWindowSeconds
        );
        if (! $allowed) {
            $this->respond([
                'ok'      => false,
                'mode'    => 'error',
                'message' => 'Too many requests. Please wait a moment and try again.',
            ], 429);

            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function respond(array $payload, int $status = 200)
    {
        $payload['csrf'] = csrf_hash();
        $body = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
        $this->response->setStatusCode($status);
        $this->response->setContentType('application/json');
        $this->response->setHeader('X-Content-Type-Options', 'nosniff');
        $this->response->setHeader('Cache-Control', 'no-store');
        $this->response->setBody($body === false ? '{}' : $body);

        return $this->response;
    }
}
