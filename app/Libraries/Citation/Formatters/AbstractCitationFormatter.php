<?php

declare(strict_types=1);

namespace App\Libraries\Citation\Formatters;

use App\Libraries\Citation\CitationRecord;

abstract class AbstractCitationFormatter implements CitationFormatterInterface
{
    public function inTextLabel(): string
    {
        return 'In-text citation';
    }

    public function warnings(CitationRecord $record): array
    {
        $warnings = [];
        if ($record->authors === []) {
            $warnings[] = 'Author names could not be verified.';
        }
        if ($record->year === '') {
            $warnings[] = 'Publication year could not be verified.';
        }
        if ($record->title === '') {
            $warnings[] = 'Title could not be verified.';
        }
        if ($this->isArticle($record)) {
            if ($record->journal === '') {
                $warnings[] = 'Journal name could not be verified.';
            }
            if ($record->volume === '') {
                $warnings[] = 'Volume could not be verified.';
            }
            if ($record->issue === '') {
                $warnings[] = 'Issue could not be verified.';
            }
            if ($record->pages === '') {
                $warnings[] = 'Page numbers could not be verified.';
            }
            if ($record->doi === '') {
                $warnings[] = 'DOI could not be verified.';
            }
        } elseif ($record->publisher === '') {
            $warnings[] = 'Publisher could not be verified.';
        }

        return $warnings;
    }

    protected function isArticle(CitationRecord $record): bool
    {
        if ($record->type === 'book') {
            return false;
        }
        if ($record->type === 'article') {
            return true;
        }

        return $record->journal !== '';
    }

    protected function terminate(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        if (preg_match('/[.?!]$/u', $text) === 1) {
            return $text;
        }

        return $text . '.';
    }

    protected function normalizePages(string $pages): string
    {
        $pages = trim($pages);
        if ($pages === '') {
            return '';
        }
        $pages = str_replace(['—', '–'], '-', $pages);
        if (preg_match('/^([A-Za-z0-9]+)\s*-\s*([A-Za-z0-9]+)$/', $pages, $matches) === 1) {
            return $matches[1] . '–' . $matches[2];
        }

        return $pages;
    }

    protected function initials(string $given): string
    {
        $given = trim($given);
        if ($given === '') {
            return '';
        }
        $words = preg_split('/\s+/u', $given) ?: [];
        $out = [];
        foreach ($words as $word) {
            $bits = [];
            foreach (explode('-', $word) as $piece) {
                if ($piece === '') {
                    continue;
                }
                $bits[] = mb_strtoupper(mb_substr($piece, 0, 1)) . '.';
            }
            if ($bits !== []) {
                $out[] = implode('-', $bits);
            }
        }

        return implode(' ', $out);
    }

    protected function compactInitials(string $given): string
    {
        return str_replace(['.', ' ', '-'], '', $this->initials($given));
    }

    protected function doiUrl(CitationRecord $record): string
    {
        return $record->doi !== '' ? 'https://doi.org/' . $record->doi : '';
    }

    protected function volumeIssue(CitationRecord $record): string
    {
        if ($record->volume !== '' && $record->issue !== '') {
            return $record->volume . '(' . $record->issue . ')';
        }
        if ($record->volume !== '') {
            return $record->volume;
        }
        if ($record->issue !== '') {
            return '(' . $record->issue . ')';
        }

        return '';
    }

    protected function editionNote(CitationRecord $record): string
    {
        $edition = trim($record->edition);
        if ($edition === '') {
            return '';
        }
        if (preg_match('/ed\.?$/i', $edition) === 1) {
            return $edition;
        }

        return $edition . ' ed.';
    }

    /**
     * @return list<string>
     */
    protected function families(CitationRecord $record): array
    {
        $names = [];
        foreach ($record->authors as $author) {
            $family = trim($author['family']);
            if ($family !== '') {
                $names[] = $family;
            }
        }

        return $names;
    }

    protected function appendUrl(string $text, CitationRecord $record): string
    {
        $link = $this->doiUrl($record);
        if ($link === '' && $record->url !== '') {
            $link = $record->url;
        }
        if ($link === '') {
            return $text;
        }

        return trim($text . ' ' . $link);
    }
}
