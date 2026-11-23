<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Settings for the standalone citation generator.
 * Override values in .env. Do not put a real API key in this file.
 */
class Citation extends BaseConfig
{
    public int $timeoutSeconds = 12;

    public int $maxResponseBytes = 1048576;

    /** Normalized metadata lifetime, in seconds (7 days). */
    public int $cacheTtlSeconds = 604800;

    public int $negativeCacheTtlSeconds = 3600;

    public int $searchCacheTtlSeconds = 86400;

    /** Requests allowed per IP for lookup, search, and format. */
    public int $rateLimit = 20;

    public int $rateWindowSeconds = 60;

    public int $maxInputLength = 500;

    public int $searchLimit = 8;

    /** Email used for the Crossref and OpenAlex polite pool. Not a secret. */
    public string $crossrefMailto = '';

    /** Optional. Leave empty to use the OpenAlex polite pool. */
    public string $openAlexApiKey = '';

    public string $userAgent = 'StandaloneCitationGenerator/1.0';

    /**
     * Hosts the generator is allowed to contact. User-supplied URLs are never requested.
     *
     * @var list<string>
     */
    public array $allowedHosts = [
        'api.crossref.org',
        'api.openalex.org',
        'openlibrary.org',
        'doi.org',
        'dx.doi.org',
        'data.crosscite.org',
        'citation.crosscite.org',
        'api.datacite.org',
    ];

    public function __construct()
    {
        parent::__construct();

        $mailto = env('citation.crossrefMailto');
        if (is_string($mailto) && trim($mailto) !== '') {
            $this->crossrefMailto = trim($mailto);
        }

        $key = env('citation.openAlexApiKey');
        if (is_string($key) && trim($key) !== '') {
            $this->openAlexApiKey = trim($key);
        }

        foreach (['timeoutSeconds', 'cacheTtlSeconds', 'rateLimit'] as $name) {
            $value = env('citation.' . $name);
            if (is_numeric($value) && (int) $value > 0) {
                $this->{$name} = (int) $value;
            }
        }
    }

    public function contactEmail(): string
    {
        return (string) preg_replace('/^mailto:/i', '', trim($this->crossrefMailto));
    }
}
