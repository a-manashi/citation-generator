<?php

declare(strict_types=1);

namespace App\Libraries\Citation;

use Config\Citation;
use Throwable;

/**
 * File-cache wrapper for normalized citation metadata.
 */
class CitationCache
{
    public function __construct(private readonly Citation $config)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $key): ?array
    {
        try {
            $value = service('cache')->get($this->storageKey($key));
        } catch (Throwable $e) {
            return null;
        }

        return is_array($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $value
     */
    public function put(string $key, array $value, ?int $ttl = null): void
    {
        try {
            service('cache')->save($this->storageKey($key), $value, $ttl ?? $this->config->cacheTtlSeconds);
        } catch (Throwable $e) {
            log_message('error', 'Citation cache write failed.');
        }
    }

    private function storageKey(string $key): string
    {
        return 'citation_' . md5($key);
    }
}
