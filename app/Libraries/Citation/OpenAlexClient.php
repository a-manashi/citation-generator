<?php

declare(strict_types=1);

namespace App\Libraries\Citation;

class OpenAlexClient extends AbstractClient
{
    public function lookupDoi(string $doi): ?CitationRecord
    {
        $data = $this->getJson('https://api.openalex.org/works', $this->params([
            'filter' => 'doi:' . $doi,
        ]));
        $work = $data['results'][0] ?? null;

        return is_array($work) ? $this->mapWork($work) : null;
    }

    public function lookupIsbn(string $isbn): ?CitationRecord
    {
        $data = $this->getJson('https://api.openalex.org/works', $this->params([
            'filter' => 'isbn:' . $isbn,
        ]));
        $work = $data['results'][0] ?? null;

        return is_array($work) ? $this->mapWork($work) : null;
    }

    public function lookupId(string $workId): ?CitationRecord
    {
        if (preg_match('/^W\d{1,12}$/', $workId) !== 1) {
            return null;
        }
        $data = $this->getJson('https://api.openalex.org/works/' . $workId, $this->params());
        if (! is_array($data) || isset($data['results'])) {
            return null;
        }

        return $this->mapWork($data);
    }

    /**
     * @return list<array{id: string, provider: string, title: string, authors: string, year: string, venue: string}>
     */
    public function search(string $query, int $limit): array
    {
        $data = $this->getJson('https://api.openalex.org/works', $this->params([
            'search'   => $query,
            'per_page' => $limit,
        ]));
        $items = $data['results'] ?? null;
        if (! is_array($items)) {
            return [];
        }

        $results = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $record = $this->mapWork($item);
            $id = $this->workId($item);
            if ($record === null || $id === '') {
                continue;
            }
            $results[] = [
                'id'       => $id,
                'provider' => 'openalex',
                'title'    => $record->title,
                'authors'  => $record->authorsText(),
                'year'     => $record->year,
                'venue'    => $record->journal !== '' ? $record->journal : $record->publisher,
            ];
        }

        return $results;
    }

    /**
     * @param array<string, scalar|null> $extra
     *
     * @return array<string, scalar|null>
     */
    private function params(array $extra = []): array
    {
        $params = $extra;
        $email = $this->email();
        if ($email !== '') {
            $params['mailto'] = $email;
        }
        if ($this->config->openAlexApiKey !== '') {
            $params['api_key'] = $this->config->openAlexApiKey;
        }

        return $params;
    }

    /**
     * @param array<string, mixed> $work
     */
    private function mapWork(array $work): ?CitationRecord
    {
        $title = CitationRecord::clean((string) ($work['display_name'] ?? $work['title'] ?? ''), 500);
        if ($title === '') {
            return null;
        }

        $record = new CitationRecord();
        $record->title = $title;
        $record->authors = $this->authors($work['authorships'] ?? []);
        $record->year = CitationRecord::year((string) ($work['publication_year'] ?? ''));
        $biblio = is_array($work['biblio'] ?? null) ? $work['biblio'] : [];
        $record->volume = CitationRecord::clean((string) ($biblio['volume'] ?? ''), 40);
        $record->issue = CitationRecord::clean((string) ($biblio['issue'] ?? ''), 40);
        $first = CitationRecord::clean((string) ($biblio['first_page'] ?? ''), 20);
        $last = CitationRecord::clean((string) ($biblio['last_page'] ?? ''), 20);
        if ($first !== '' && $last !== '' && $first !== $last) {
            $record->pages = $first . '-' . $last;
        } else {
            $record->pages = $first;
        }

        $location = is_array($work['primary_location'] ?? null) ? $work['primary_location'] : [];
        $source = is_array($location['source'] ?? null) ? $location['source'] : [];
        $record->journal = CitationRecord::clean((string) ($source['display_name'] ?? ''), 300);
        $record->publisher = CitationRecord::clean((string) ($source['host_organization_name'] ?? ''), 200);
        $record->type = CitationRecord::mapType((string) ($work['type'] ?? ''));
        if ($record->type === '' && $record->journal !== '') {
            $record->type = 'article';
        }
        $doi = (new InputDetector())->normalizeDoi((string) ($work['doi'] ?? ''));
        $record->doi = $doi ?? '';
        $record->url = $record->doi !== '' ? 'https://doi.org/' . $record->doi : '';
        $record->source = 'openalex';

        return $record;
    }

    /**
     * @param mixed $authorships
     *
     * @return list<array{given: string, family: string}>
     */
    private function authors(mixed $authorships): array
    {
        if (! is_array($authorships)) {
            return [];
        }
        $authors = [];
        foreach ($authorships as $ship) {
            if (! is_array($ship)) {
                continue;
            }
            $author = is_array($ship['author'] ?? null) ? $ship['author'] : [];
            $name = (string) ($author['display_name'] ?? $ship['raw_author_name'] ?? '');
            $parsed = CitationRecord::authorsFromText($name);
            if ($parsed !== []) {
                $authors[] = $parsed[0];
            }
        }

        return CitationRecord::limitAuthors($authors);
    }

    /**
     * @param array<string, mixed> $work
     */
    private function workId(array $work): string
    {
        $id = (string) ($work['id'] ?? '');
        if (preg_match('~(W\d{1,12})$~', $id, $matches) === 1) {
            return $matches[1];
        }

        return '';
    }
}
