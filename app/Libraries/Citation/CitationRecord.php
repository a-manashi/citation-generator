<?php

declare(strict_types=1);

namespace App\Libraries\Citation;

/**
 * One bibliographic record, regardless of which provider supplied it.
 */
class CitationRecord
{
    public string $title = '';

    /** @var list<array{given: string, family: string}> */
    public array $authors = [];

    public string $year = '';

    public string $month = '';

    public string $day = '';

    public string $journal = '';

    public string $volume = '';

    public string $issue = '';

    public string $pages = '';

    public string $publisher = '';

    public string $edition = '';

    public string $isbn = '';

    public string $doi = '';

    public string $url = '';

    public string $type = '';

    public string $source = '';

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $record = new self();
        $detector = new InputDetector();

        $record->title = self::clean((string) ($data['title'] ?? ''), 500);
        $record->journal = self::clean((string) ($data['journal'] ?? ''), 300);
        $record->volume = self::clean((string) ($data['volume'] ?? ''), 40);
        $record->issue = self::clean((string) ($data['issue'] ?? ''), 40);
        $record->pages = self::clean((string) ($data['pages'] ?? ''), 50);
        $record->publisher = self::clean((string) ($data['publisher'] ?? ''), 200);
        $record->edition = self::clean((string) ($data['edition'] ?? ''), 40);
        $record->month = self::numericPart((string) ($data['month'] ?? ''), 1, 12);
        $record->day = self::numericPart((string) ($data['day'] ?? ''), 1, 31);
        $record->year = self::year((string) ($data['year'] ?? ''));
        $record->url = self::cleanUrl((string) ($data['url'] ?? ''));

        $doi = $detector->normalizeDoi((string) ($data['doi'] ?? ''));
        $record->doi = $doi ?? '';

        $isbn = $detector->normalizeIsbn((string) ($data['isbn'] ?? ''));
        $record->isbn = $isbn ?? '';

        $type = strtolower(self::clean((string) ($data['type'] ?? ''), 20));
        $record->type = in_array($type, ['article', 'book', 'other'], true) ? $type : '';

        $source = strtolower(self::clean((string) ($data['source'] ?? ''), 20));
        $record->source = in_array($source, ['crossref', 'openalex', 'openlibrary', 'doicsl', 'manual'], true) ? $source : '';

        if (isset($data['authors_text']) && is_string($data['authors_text'])) {
            $record->authors = self::authorsFromText($data['authors_text']);
        } elseif (isset($data['authors']) && is_string($data['authors'])) {
            $record->authors = self::authorsFromText($data['authors']);
        } elseif (isset($data['authors']) && is_array($data['authors'])) {
            $record->authors = self::authorsFromList($data['authors']);
        }

        return $record;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title'     => $this->title,
            'authors'   => $this->authors,
            'year'      => $this->year,
            'month'     => $this->month,
            'day'       => $this->day,
            'journal'   => $this->journal,
            'volume'    => $this->volume,
            'issue'     => $this->issue,
            'pages'     => $this->pages,
            'publisher' => $this->publisher,
            'edition'   => $this->edition,
            'isbn'      => $this->isbn,
            'doi'       => $this->doi,
            'url'       => $this->url,
            'type'      => $this->type,
            'source'    => $this->source,
        ];
    }

    public function authorsText(): string
    {
        $parts = [];
        foreach ($this->authors as $author) {
            $name = trim($author['given'] . ' ' . $author['family']);
            if ($name !== '') {
                $parts[] = $name;
            }
        }

        return implode('; ', $parts);
    }

    /**
     * @return list<array{given: string, family: string}>
     */
    public static function authorsFromText(string $text): array
    {
        $text = self::clean($text, 2000);
        if ($text === '') {
            return [];
        }

        $chunks = preg_split('/\s*;\s*/u', $text) ?: [];
        $authors = [];
        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }
            if (str_contains($chunk, ',')) {
                [$family, $given] = array_map('trim', explode(',', $chunk, 2));
                $authors[] = [
                    'given'  => self::clean($given, 200),
                    'family' => self::clean($family, 200),
                ];
            } else {
                $pos = mb_strrpos($chunk, ' ');
                if ($pos === false) {
                    $authors[] = ['given' => '', 'family' => self::clean($chunk, 200)];
                } else {
                    $authors[] = [
                        'given'  => self::clean(mb_substr($chunk, 0, $pos), 200),
                        'family' => self::clean(mb_substr($chunk, $pos + 1), 200),
                    ];
                }
            }
            if (count($authors) >= 40) {
                break;
            }
        }

        return self::limitAuthors($authors);
    }

    /**
     * @param list<mixed> $authors
     *
     * @return list<array{given: string, family: string}>
     */
    public static function authorsFromList(array $authors): array
    {
        $clean = [];
        foreach ($authors as $author) {
            if (! is_array($author)) {
                continue;
            }
            $given = self::clean((string) ($author['given'] ?? ''), 200);
            $family = self::clean((string) ($author['family'] ?? ''), 200);
            if ($given === '' && $family === '') {
                continue;
            }
            $clean[] = ['given' => $given, 'family' => $family];
        }

        return self::limitAuthors($clean);
    }

    /**
     * Keep the first 29 names and the final author so long lists still format correctly.
     *
     * @param list<array{given: string, family: string}> $authors
     *
     * @return list<array{given: string, family: string}>
     */
    public static function limitAuthors(array $authors): array
    {
        $authors = array_values($authors);
        $count = count($authors);
        if ($count <= 30) {
            return $authors;
        }

        $limited = array_slice($authors, 0, 29);
        $limited[] = $authors[$count - 1];

        return $limited;
    }

    public static function clean(string $value, int $max = 500): string
    {
        $value = strip_tags($value);
        $value = str_replace(["\0", '<', '>'], '', $value);
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if (mb_strlen($value) > $max) {
            $value = mb_substr($value, 0, $max);
        }

        return $value;
    }

    public static function year(string $value): string
    {
        if (preg_match('/\b(\d{4})\b/', $value, $matches) !== 1) {
            return '';
        }
        $year = (int) $matches[1];
        if ($year < 1000 || $year > 2100) {
            return '';
        }

        return (string) $year;
    }

    public static function mapType(string $raw): string
    {
        $raw = strtolower(trim($raw));
        if ($raw === '') {
            return '';
        }
        if (str_contains($raw, 'article') || $raw === 'preprint' || $raw === 'review') {
            return 'article';
        }
        if (str_contains($raw, 'book') || $raw === 'monograph' || $raw === 'chapter') {
            return 'book';
        }

        return 'other';
    }

    private static function numericPart(string $value, int $min, int $max): string
    {
        if (preg_match('/\b(\d{1,2})\b/', $value, $matches) !== 1) {
            return '';
        }
        $number = (int) $matches[1];
        if ($number < $min || $number > $max) {
            return '';
        }

        return (string) $number;
    }

    private static function cleanUrl(string $url): string
    {
        $url = self::clean($url, 500);
        if ($url === '') {
            return '';
        }
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return '';
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return '';
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return '';
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local')) {
            return '';
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return '';
        }

        return $url;
    }
}
