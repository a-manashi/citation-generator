<?php

declare(strict_types=1);

namespace App\Libraries\Citation;

class CrossrefClient extends AbstractClient
{
    public function lookupDoi(string $doi): ?CitationRecord
    {
        $url = 'https://api.crossref.org/works/' . $this->encodeDoi($doi);
        $data = $this->getJson($url, ['mailto' => $this->email()]);
        if ($data === null || ! isset($data['message']) || ! is_array($data['message'])) {
            return null;
        }

        return $this->mapWork($data['message']);
    }

    /**
     * @return list<array{id: string, provider: string, title: string, authors: string, year: string, venue: string}>
     */
    public function search(string $query, int $limit): array
    {
        $data = $this->getJson('https://api.crossref.org/works', [
            'query.bibliographic' => $query,
            'rows'                => $limit,
            'mailto'              => $this->email(),
        ]);
        $items = $data['message']['items'] ?? null;
        if (! is_array($items)) {
            return [];
        }

        $results = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $record = $this->mapWork($item);
            if ($record === null || $record->doi === '') {
                continue;
            }
            $results[] = [
                'id'       => $record->doi,
                'provider' => 'crossref',
                'title'    => $record->title,
                'authors'  => $record->authorsText(),
                'year'     => $record->year,
                'venue'    => $record->journal !== '' ? $record->journal : $record->publisher,
            ];
            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $work
     */
    private function mapWork(array $work): ?CitationRecord
    {
        $title = $this->firstString($work['title'] ?? '');
        if ($title === '') {
            return null;
        }

        $record = new CitationRecord();
        $record->title = $title;
        $record->authors = $this->authors($work['author'] ?? []);
        [$record->year, $record->month, $record->day] = $this->issued($work);
        $record->journal = $this->firstString($work['container-title'] ?? '');
        $record->volume = CitationRecord::clean((string) ($work['volume'] ?? ''), 40);
        $record->issue = CitationRecord::clean((string) ($work['issue'] ?? ''), 40);
        $record->pages = CitationRecord::clean((string) ($work['page'] ?? ''), 50);
        $record->publisher = CitationRecord::clean((string) ($work['publisher'] ?? ''), 200);
        $record->type = CitationRecord::mapType((string) ($work['type'] ?? ''));
        if ($record->type === '' && $record->journal !== '') {
            $record->type = 'article';
        }
        $isbn = $this->firstString($work['ISBN'] ?? '');
        $normalizedIsbn = (new InputDetector())->normalizeIsbn($isbn);
        $record->isbn = $normalizedIsbn ?? '';
        $doi = (new InputDetector())->normalizeDoi((string) ($work['DOI'] ?? ''));
        $record->doi = $doi ?? '';
        $record->url = $record->doi !== '' ? 'https://doi.org/' . $record->doi : '';
        $record->source = 'crossref';

        return $record;
    }

    /**
     * @param mixed $authors
     *
     * @return list<array{given: string, family: string}>
     */
    private function authors(mixed $authors): array
    {
        if (! is_array($authors)) {
            return [];
        }
        $parsed = [];
        foreach ($authors as $author) {
            if (! is_array($author)) {
                continue;
            }
            $given = CitationRecord::clean((string) ($author['given'] ?? ''), 200);
            $family = CitationRecord::clean((string) ($author['family'] ?? ''), 200);
            if ($family === '' && isset($author['name'])) {
                $fromName = CitationRecord::authorsFromText((string) $author['name']);
                if ($fromName !== []) {
                    $parsed[] = $fromName[0];
                }
                continue;
            }
            if ($family === '' && $given !== '') {
                $fromName = CitationRecord::authorsFromText($given);
                if ($fromName !== []) {
                    $parsed[] = $fromName[0];
                }
                continue;
            }
            if ($family !== '' || $given !== '') {
                $parsed[] = ['given' => $given, 'family' => $family];
            }
        }

        return CitationRecord::limitAuthors($parsed);
    }

    /**
     * @param array<string, mixed> $work
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function issued(array $work): array
    {
        foreach (['issued', 'published-print', 'published-online'] as $key) {
            if (! isset($work[$key]) || ! is_array($work[$key])) {
                continue;
            }
            $parts = $this->dateParts($work[$key]['date-parts'] ?? null);
            if ($parts[0] !== '') {
                return $parts;
            }
        }

        return ['', '', ''];
    }

    private function encodeDoi(string $doi): string
    {
        return str_replace('%2F', '/', rawurlencode($doi));
    }
}
