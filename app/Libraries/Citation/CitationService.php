<?php

declare(strict_types=1);

namespace App\Libraries\Citation;

use Config\Citation;

/**
 * Looks up bibliographic metadata and formats it. Formatting never calls an API.
 */
class CitationService
{
    private Citation $config;

    private CitationHttp $http;

    private CitationCache $cache;

    private InputDetector $detector;

    private CrossrefClient $crossref;

    private OpenAlexClient $openAlex;

    private OpenLibraryClient $openLibrary;

    private DoiCslClient $doiCsl;

    public function __construct(
        ?Citation $config = null,
        ?CitationHttp $http = null,
        ?CitationCache $cache = null,
        ?InputDetector $detector = null,
    ) {
        $this->config = $config ?? config(Citation::class);
        $this->http = $http ?? new CitationHttp($this->config);
        $this->cache = $cache ?? new CitationCache($this->config);
        $this->detector = $detector ?? new InputDetector();
        $this->crossref = new CrossrefClient($this->http, $this->config);
        $this->openAlex = new OpenAlexClient($this->http, $this->config);
        $this->openLibrary = new OpenLibraryClient($this->http, $this->config);
        $this->doiCsl = new DoiCslClient($this->http, $this->config);
    }

    /**
     * @return array<string, mixed>
     */
    public function lookup(string $query, string $style): array
    {
        try {
            return $this->dispatch($query, $style);
        } catch (CitationException $e) {
            return $this->fail($e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function search(string $query, string $style): array
    {
        return $this->lookup($query, $style);
    }

    /**
     * @return array<string, mixed>
     */
    public function select(string $provider, string $workId, string $style): array
    {
        try {
            $formatter = FormatterFactory::make($style);
            $provider = strtolower(trim($provider));
            $workId = trim($workId);
            $record = match ($provider) {
                'openalex'    => $this->fetchOpenAlex($workId),
                'openlibrary' => $this->fetchOpenLibrary($workId),
                'crossref'    => $this->fetchDoi($this->requireDoi($workId)),
                default       => throw new CitationException(
                    'That publication could not be selected. Please search again.',
                    CitationException::BAD_RESPONSE
                ),
            };

            return $this->present($record, $formatter);
        } catch (CitationException $e) {
            return $this->fail($e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function format(array $input, string $style): array
    {
        try {
            $formatter = FormatterFactory::make($style);
            $record = CitationRecord::fromArray($input);
            $record->source = 'manual';

            return $this->present($record, $formatter);
        } catch (CitationException $e) {
            return $this->fail($e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function dispatch(string $query, string $style): array
    {
        $formatter = FormatterFactory::make($style);
        if (mb_strlen($query) > $this->config->maxInputLength) {
            return $this->fail('Enter a shorter DOI, ISBN, or title.');
        }

        $detected = $this->detector->detect($query);

        return match ($detected['type']) {
            InputDetector::TYPE_EMPTY => $this->fail('Enter a DOI, ISBN, or title.'),
            InputDetector::TYPE_INVALID_DOI => $this->fail('That DOI does not look valid. Check the number and try again.'),
            InputDetector::TYPE_INVALID_ISBN => $this->fail('That ISBN does not look valid. Check the number and try again.'),
            InputDetector::TYPE_URL => $this->fail('We can\'t retrieve a page from a general web address. Paste the DOI, ISBN, or title instead.'),
            InputDetector::TYPE_SEARCH => $this->searchPublications($detected['value']),
            InputDetector::TYPE_DOI => $this->present($this->fetchDoi($detected['value']), $formatter),
            InputDetector::TYPE_ISBN => $this->present($this->fetchIsbn($detected['value']), $formatter),
            default => $this->fail('Enter a DOI, ISBN, or title.'),
        };
    }

    private function fetchDoi(string $doi): CitationRecord
    {
        $key = 'doi:' . strtolower($doi);
        $cached = $this->cachedRecord($key);
        if ($cached instanceof CitationRecord) {
            return $cached;
        }

        try {
            $record = $this->firstAvailable([
                fn (): ?CitationRecord => $this->crossref->lookupDoi($doi),
                fn (): ?CitationRecord => $this->doiCsl->lookupDoi($doi),
                fn (): ?CitationRecord => $this->openAlex->lookupDoi($doi),
            ]);
        } catch (CitationException $e) {
            if ($e->getCode() === CitationException::NOT_FOUND) {
                $this->cache->put($key, ['_miss' => true], $this->config->negativeCacheTtlSeconds);
            }
            throw $e;
        }

        $this->cache->put($key, $record->toArray());

        return $record;
    }

    private function fetchIsbn(string $isbn): CitationRecord
    {
        $key = 'isbn:' . $isbn;
        $cached = $this->cachedRecord($key);
        if ($cached instanceof CitationRecord) {
            return $cached;
        }

        try {
            $record = $this->firstAvailable([
                fn (): ?CitationRecord => $this->openLibrary->lookupIsbn($isbn),
                fn (): ?CitationRecord => $this->openAlex->lookupIsbn($isbn),
            ]);
        } catch (CitationException $e) {
            if ($e->getCode() === CitationException::NOT_FOUND) {
                $this->cache->put($key, ['_miss' => true], $this->config->negativeCacheTtlSeconds);
            }
            throw $e;
        }

        $this->cache->put($key, $record->toArray());

        return $record;
    }

    private function fetchOpenAlex(string $workId): CitationRecord
    {
        if (preg_match('/^W\d{1,12}$/', $workId) !== 1) {
            throw new CitationException(
                'That publication could not be selected. Please search again.',
                CitationException::BAD_RESPONSE
            );
        }
        $key = 'openalex:' . $workId;
        $cached = $this->cachedRecord($key);
        if ($cached instanceof CitationRecord) {
            return $cached;
        }
        $record = $this->openAlex->lookupId($workId);
        if (! $record instanceof CitationRecord || $record->title === '') {
            throw new CitationException(
                'No publication was found for that identifier.',
                CitationException::NOT_FOUND
            );
        }
        $this->cache->put($key, $record->toArray());

        return $record;
    }

    private function fetchOpenLibrary(string $workId): CitationRecord
    {
        if (preg_match('/^OL\d{1,12}W$/', $workId) !== 1) {
            throw new CitationException(
                'That publication could not be selected. Please search again.',
                CitationException::BAD_RESPONSE
            );
        }
        $key = 'openlibrary:' . $workId;
        $cached = $this->cachedRecord($key);
        if ($cached instanceof CitationRecord) {
            return $cached;
        }
        $record = $this->openLibrary->lookupWork($workId);
        if (! $record instanceof CitationRecord || $record->title === '') {
            throw new CitationException(
                'No publication was found for that identifier.',
                CitationException::NOT_FOUND
            );
        }
        $this->cache->put($key, $record->toArray());

        return $record;
    }

    /**
     * @return array<string, mixed>
     */
    private function searchPublications(string $query): array
    {
        if (mb_strlen($query) < 2) {
            return $this->fail('Enter a more specific title.');
        }

        $cacheKey = 'search:' . mb_strtolower($query);
        $cached = $this->cache->get($cacheKey);
        if (is_array($cached) && isset($cached['results']) && is_array($cached['results'])) {
            return $this->resultsFrom($cached['results']);
        }

        $hard = null;
        $results = [];
        try {
            $results = $this->openAlex->search($query, $this->config->searchLimit);
        } catch (CitationException $e) {
            $hard = $e;
        }
        if ($results === []) {
            try {
                $results = $this->crossref->search($query, $this->config->searchLimit);
                $hard = null;
            } catch (CitationException $e) {
                $hard ??= $e;
            }
        }
        if ($results === [] && $hard instanceof CitationException) {
            throw $hard;
        }

        $ttl = $results === [] ? $this->config->negativeCacheTtlSeconds : $this->config->searchCacheTtlSeconds;
        $clean = $this->cleanResults($results);
        $this->cache->put($cacheKey, ['results' => $clean], $ttl);

        return $this->resultsFrom($clean);
    }

    /**
     * @param list<callable(): ?CitationRecord> $attempts
     */
    private function firstAvailable(array $attempts): CitationRecord
    {
        $hard = null;
        $notFound = 0;
        foreach ($attempts as $attempt) {
            try {
                $record = $attempt();
                if ($record instanceof CitationRecord && $record->title !== '') {
                    return $record;
                }
                $notFound++;
            } catch (CitationException $e) {
                if (in_array($e->getCode(), [CitationException::NOT_FOUND, CitationException::SKIP], true)) {
                    $notFound++;
                } else {
                    $hard = $e;
                }
            }
        }
        if ($notFound === count($attempts)) {
            throw new CitationException(
                'No publication was found for that identifier.',
                CitationException::NOT_FOUND
            );
        }
        if ($hard instanceof CitationException) {
            throw $hard;
        }

        throw new CitationException(
            'No publication was found for that identifier.',
            CitationException::NOT_FOUND
        );
    }

    private function cachedRecord(string $key): ?CitationRecord
    {
        $cached = $this->cache->get($key);
        if ($cached === null) {
            return null;
        }
        if (! empty($cached['_miss'])) {
            throw new CitationException(
                'No publication was found for that identifier.',
                CitationException::NOT_FOUND
            );
        }
        $record = CitationRecord::fromArray($cached);

        return $record->title !== '' ? $record : null;
    }

    private function requireDoi(string $value): string
    {
        $doi = $this->detector->normalizeDoi($value);
        if ($doi === null) {
            throw new CitationException(
                'That publication could not be selected. Please search again.',
                CitationException::BAD_RESPONSE
            );
        }

        return $doi;
    }

    /**
     * @param list<array<string, mixed>> $results
     *
     * @return list<array{id: string, provider: string, title: string, authors: string, year: string, venue: string}>
     */
    private function cleanResults(array $results): array
    {
        $clean = [];
        foreach ($results as $row) {
            if (! is_array($row)) {
                continue;
            }
            $provider = (string) ($row['provider'] ?? '');
            $id = (string) ($row['id'] ?? '');
            if ($provider === 'openalex' && preg_match('/^W\d{1,12}$/', $id) !== 1) {
                continue;
            }
            if ($provider === 'openlibrary' && preg_match('/^OL\d{1,12}W$/', $id) !== 1) {
                continue;
            }
            if ($provider === 'crossref') {
                $doi = $this->detector->normalizeDoi($id);
                if ($doi === null) {
                    continue;
                }
                $id = $doi;
            }
            if (! in_array($provider, ['openalex', 'openlibrary', 'crossref'], true)) {
                continue;
            }
            $clean[] = [
                'id'       => $id,
                'provider' => $provider,
                'title'    => CitationRecord::clean((string) ($row['title'] ?? ''), 500),
                'authors'  => CitationRecord::clean((string) ($row['authors'] ?? ''), 500),
                'year'     => CitationRecord::year((string) ($row['year'] ?? '')),
                'venue'    => CitationRecord::clean((string) ($row['venue'] ?? ''), 300),
            ];
        }

        return $clean;
    }

    /**
     * @param list<array<string, mixed>> $results
     *
     * @return array<string, mixed>
     */
    private function resultsFrom(array $results): array
    {
        $results = $this->cleanResults($results);
        if ($results === []) {
            return $this->fail('No matching publications were found. Try a DOI, ISBN, or a more specific title.');
        }

        return [
            'ok'      => true,
            'mode'    => 'results',
            'message' => 'Select the publication that matches your source.',
            'results' => $results,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(CitationRecord $record, Formatters\CitationFormatterInterface $formatter): array
    {
        return [
            'ok'            => true,
            'mode'          => 'citation',
            'message'       => '',
            'record'        => $record->toArray(),
            'authors_text'  => $record->authorsText(),
            'bibliography'  => $formatter->bibliography($record),
            'in_text'       => $formatter->inText($record),
            'in_text_label' => $formatter->inTextLabel(),
            'warnings'      => $formatter->warnings($record),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fail(string $message): array
    {
        return [
            'ok'      => false,
            'mode'    => 'error',
            'message' => $message,
            'results' => [],
        ];
    }
}
