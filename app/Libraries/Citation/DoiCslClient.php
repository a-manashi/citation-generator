<?php

declare(strict_types=1);

namespace App\Libraries\Citation;

/**
 * DOI content negotiation for CSL-JSON. Redirects are limited to known DOI hosts.
 */
class DoiCslClient extends AbstractClient
{
    public function lookupDoi(string $doi): ?CitationRecord
    {
        $encoded = str_replace('%2F', '/', rawurlencode($doi));
        $data = $this->getJson('https://doi.org/' . $encoded, [], [
            'Accept: application/vnd.citationstyles.csl+json',
        ]);
        if ($data === null) {
            return null;
        }

        $title = $this->firstString($data['title'] ?? '');
        if ($title === '') {
            return null;
        }

        $record = new CitationRecord();
        $record->title = $title;
        $record->authors = $this->authors($data['author'] ?? []);
        [$record->year, $record->month, $record->day] = $this->dateParts($data['issued']['date-parts'] ?? null);
        $record->journal = $this->firstString($data['container-title'] ?? '');
        $record->volume = CitationRecord::clean((string) ($data['volume'] ?? ''), 40);
        $record->issue = CitationRecord::clean((string) ($data['issue'] ?? ''), 40);
        $record->pages = CitationRecord::clean((string) ($data['page'] ?? ''), 50);
        $record->publisher = CitationRecord::clean((string) ($data['publisher'] ?? ''), 200);
        $record->edition = CitationRecord::clean((string) ($data['edition'] ?? ''), 40);
        $record->type = CitationRecord::mapType((string) ($data['type'] ?? ''));
        if ($record->type === '' && $record->journal !== '') {
            $record->type = 'article';
        }
        $isbn = $this->firstString($data['ISBN'] ?? '');
        $normalizedIsbn = (new InputDetector())->normalizeIsbn($isbn);
        $record->isbn = $normalizedIsbn ?? '';
        $normalizedDoi = (new InputDetector())->normalizeDoi((string) ($data['DOI'] ?? $doi));
        $record->doi = $normalizedDoi ?? '';
        $record->url = $record->doi !== '' ? 'https://doi.org/' . $record->doi : '';
        $record->source = 'doicsl';

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
            if (! empty($author['literal'])) {
                $fromName = CitationRecord::authorsFromText((string) $author['literal']);
                if ($fromName !== []) {
                    $parsed[] = $fromName[0];
                }
                continue;
            }
            $given = CitationRecord::clean((string) ($author['given'] ?? ''), 200);
            $family = CitationRecord::clean((string) ($author['family'] ?? ''), 200);
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
}
