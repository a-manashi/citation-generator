<?php

declare(strict_types=1);

namespace App\Libraries\Citation;

class OpenLibraryClient extends AbstractClient
{
    private InputDetector $detector;

    public function __construct(CitationHttp $http, \Config\Citation $config)
    {
        parent::__construct($http, $config);
        $this->detector = new InputDetector();
    }

    public function lookupIsbn(string $isbn): ?CitationRecord
    {
        $data = $this->getJson('https://openlibrary.org/isbn/' . rawurlencode($isbn) . '.json');
        if ($data === null) {
            return null;
        }

        return $this->mapEdition($data, $isbn);
    }

    public function lookupWork(string $workId): ?CitationRecord
    {
        if (preg_match('/^OL\d{1,12}W$/', $workId) !== 1) {
            return null;
        }
        $work = $this->getJson('https://openlibrary.org/works/' . $workId . '.json');
        if ($work === null) {
            return null;
        }

        $record = new CitationRecord();
        $record->title = CitationRecord::clean((string) ($work['title'] ?? ''), 500);
        $record->authors = $this->authorsFromList($this->authorEntries($work['authors'] ?? []));
        $record->type = 'book';
        $record->source = 'openlibrary';

        try {
            $editions = $this->getJson('https://openlibrary.org/works/' . $workId . '/editions.json', ['limit' => 1]);
            $entry = $editions['entries'][0] ?? null;
            if (is_array($entry)) {
                $this->applyEdition($record, $entry);
            }
        } catch (CitationException $e) {
            // The work title can still be cited when edition details are unavailable.
        }

        return $record->title === '' ? null : $record;
    }

    /**
     * @param array<string, mixed> $edition
     */
    private function mapEdition(array $edition, string $isbn): ?CitationRecord
    {
        $record = new CitationRecord();
        $record->title = CitationRecord::clean((string) ($edition['title'] ?? ''), 500);
        if ($record->title === '') {
            return null;
        }
        $record->authors = $this->authorsFromList($edition['authors'] ?? []);
        $this->applyEdition($record, $edition);
        $record->isbn = $isbn;
        $record->type = 'book';
        $record->source = 'openlibrary';

        return $record;
    }

    /**
     * @param array<string, mixed> $edition
     */
    private function applyEdition(CitationRecord $record, array $edition): void
    {
        if ($record->publisher === '') {
            $record->publisher = $this->firstString($edition['publishers'] ?? '');
        }
        if ($record->year === '') {
            $record->year = CitationRecord::year((string) ($edition['publish_date'] ?? ''));
        }
        if ($record->edition === '') {
            $record->edition = CitationRecord::clean((string) ($edition['edition_name'] ?? $edition['edition'] ?? ''), 40);
        }
        if ($record->isbn === '') {
            $candidate = $this->firstString($edition['isbn_13'] ?? ($edition['isbn_10'] ?? ''));
            $normalized = $this->detector->normalizeIsbn($candidate);
            $record->isbn = $normalized ?? '';
        }
        if ($record->pages === '' && isset($edition['number_of_pages'])) {
            $record->pages = CitationRecord::clean((string) $edition['number_of_pages'], 20);
        }
    }

    /**
     * @param mixed $authors
     *
     * @return list<array{given: string, family: string}>
     */
    private function authorsFromList(mixed $authors): array
    {
        if (! is_array($authors)) {
            return [];
        }
        $parsed = [];
        foreach ($authors as $author) {
            if (! is_array($author)) {
                continue;
            }
            if (! empty($author['name']) && is_string($author['name'])) {
                $fromName = CitationRecord::authorsFromText($author['name']);
                if ($fromName !== []) {
                    $parsed[] = $fromName[0];
                }
                continue;
            }
            $key = (string) ($author['key'] ?? '');
            if (preg_match('~^/authors/(OL\d+A)$~', $key, $matches) !== 1) {
                continue;
            }
            $name = $this->authorName($matches[1]);
            if ($name === '') {
                continue;
            }
            $fromName = CitationRecord::authorsFromText($name);
            if ($fromName !== []) {
                $parsed[] = $fromName[0];
            }
        }

        return CitationRecord::limitAuthors($parsed);
    }

    /**
     * @param mixed $authors
     *
     * @return list<array<string, mixed>>
     */
    private function authorEntries(mixed $authors): array
    {
        if (! is_array($authors)) {
            return [];
        }
        $entries = [];
        foreach ($authors as $author) {
            if (! is_array($author)) {
                continue;
            }
            if (isset($author['author']) && is_array($author['author'])) {
                $entries[] = $author['author'];
            } else {
                $entries[] = $author;
            }
        }

        return $entries;
    }

    private function authorName(string $key): string
    {
        try {
            $data = $this->getJson('https://openlibrary.org/authors/' . $key . '.json');
        } catch (CitationException $e) {
            return '';
        }
        if ($data === null) {
            return '';
        }

        return CitationRecord::clean((string) ($data['name'] ?? $data['personal_name'] ?? ''), 200);
    }
}
