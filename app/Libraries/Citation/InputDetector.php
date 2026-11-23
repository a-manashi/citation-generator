<?php

declare(strict_types=1);

namespace App\Libraries\Citation;

/**
 * Decides whether the user entered a DOI, ISBN, URL, or title search.
 */
class InputDetector
{
    public const TYPE_DOI = 'doi';

    public const TYPE_ISBN = 'isbn';

    public const TYPE_URL = 'url';

    public const TYPE_SEARCH = 'search';

    public const TYPE_EMPTY = 'empty';

    public const TYPE_INVALID_DOI = 'invalid_doi';

    public const TYPE_INVALID_ISBN = 'invalid_isbn';

    /**
     * @return array{type: string, value: string}
     */
    public function detect(string $raw): array
    {
        $value = trim(preg_replace('/\s+/u', ' ', $raw) ?? '');
        if ($value === '') {
            return ['type' => self::TYPE_EMPTY, 'value' => ''];
        }

        if (preg_match('~^https?://~i', $value) === 1) {
            $doi = $this->doiFromUrl($value);
            if ($doi !== null) {
                return ['type' => self::TYPE_DOI, 'value' => $doi];
            }

            return ['type' => self::TYPE_URL, 'value' => $value];
        }

        $doi = $this->normalizeDoi($value);
        if ($doi !== null && $this->isEntireDoi($value, $doi)) {
            return ['type' => self::TYPE_DOI, 'value' => $doi];
        }
        if ($this->looksLikeDoiAttempt($value)) {
            return ['type' => self::TYPE_INVALID_DOI, 'value' => $value];
        }

        $isbn = $this->normalizeIsbn($value);
        if ($isbn !== null) {
            return ['type' => self::TYPE_ISBN, 'value' => $isbn];
        }
        if ($this->looksLikeIsbn($value)) {
            return ['type' => self::TYPE_INVALID_ISBN, 'value' => $value];
        }

        return ['type' => self::TYPE_SEARCH, 'value' => $value];
    }

    public function normalizeDoi(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('~10\.\d{4,9}/[^\s<>"\']+~i', $value, $matches) !== 1) {
            return null;
        }
        $doi = rtrim($matches[0], ".,);]>'\"}");
        $doi = preg_replace('/\s+/', '', $doi) ?? $doi;

        return $doi !== '' ? $doi : null;
    }

    public function normalizeIsbn(string $value): ?string
    {
        $compact = $this->isbnCompact($value);
        if ($compact === null) {
            return null;
        }
        if (strlen($compact) === 13 && $this->isbn13Valid($compact)) {
            return $compact;
        }
        if (strlen($compact) === 10 && $this->isbn10Valid($compact)) {
            return $compact;
        }

        return null;
    }

    public function looksLikeIsbn(string $value): bool
    {
        $compact = $this->isbnCompact($value);

        return $compact !== null && (strlen($compact) === 10 || strlen($compact) === 13);
    }

    private function doiFromUrl(string $url): ?string
    {
        $doi = $this->normalizeDoi($url);
        if ($doi === null) {
            return null;
        }
        if (preg_match('~doi\.org/|dx\.doi\.org/~i', $url) === 1) {
            return $doi;
        }
        if (preg_match('~[?&](?:doi|id)=10\.\d{4,9}/~i', $url) === 1) {
            return $doi;
        }

        return null;
    }

    private function isEntireDoi(string $value, string $doi): bool
    {
        $stripped = preg_replace('~^(?:doi:\s*|https?://(?:dx\.)?doi\.org/)~i', '', trim($value)) ?? trim($value);
        $stripped = rtrim($stripped, ".,);]>'\"}");

        return strcasecmp($stripped, $doi) === 0;
    }

    private function looksLikeDoiAttempt(string $value): bool
    {
        return preg_match('~^(?:doi:\s*|https?://(?:dx\.)?doi\.org/)?10\.\d+/\S+$~i', rtrim($value, ".,);]")) === 1
            || preg_match('~^(?:doi:\s*|https?://(?:dx\.)?doi\.org/)\S*$~i', $value) === 1;
    }

    private function isbnCompact(string $value): ?string
    {
        $stripped = trim($value);
        $stripped = preg_replace('/^(?:isbn(?:-1[03])?)\s*:?\s*/i', '', $stripped) ?? $stripped;
        $stripped = str_replace([' ', '-'], '', $stripped);
        if (preg_match('/^[0-9]{9}[0-9Xx]$/', $stripped) === 1 || preg_match('/^[0-9]{13}$/', $stripped) === 1) {
            return strtoupper($stripped);
        }

        return null;
    }

    private function isbn10Valid(string $isbn): bool
    {
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            if (! ctype_digit($isbn[$i])) {
                return false;
            }
            $sum += (10 - $i) * (int) $isbn[$i];
        }
        $last = $isbn[9] === 'X' ? 10 : (ctype_digit($isbn[9]) ? (int) $isbn[9] : -1);
        if ($last < 0) {
            return false;
        }
        $sum += $last;

        return $sum % 11 === 0;
    }

    private function isbn13Valid(string $isbn): bool
    {
        if (! ctype_digit($isbn)) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 13; $i++) {
            $sum += (int) $isbn[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return $sum % 10 === 0;
    }
}
