<?php

declare(strict_types=1);

namespace App\Libraries\Citation\Formatters;

use App\Libraries\Citation\CitationRecord;

class IeeeFormatter extends AbstractCitationFormatter
{
    public function bibliography(CitationRecord $record): string
    {
        $authors = $this->bibliographyAuthors($record);
        $lead = '[1]';
        if ($authors !== '') {
            $lead .= ' ' . $authors . ',';
        }
        $title = str_replace('"', "'", trim($record->title));
        if ($title !== '') {
            if ($this->isArticle($record)) {
                $lead .= ' "' . $title . ',"';
            } else {
                $lead .= ' ' . $this->terminate($title);
            }
        }

        $bits = [];
        if ($this->isArticle($record)) {
            if ($record->journal !== '') {
                $bits[] = $record->journal;
            }
            if ($record->volume !== '') {
                $bits[] = 'vol. ' . $record->volume;
            }
            if ($record->issue !== '') {
                $bits[] = 'no. ' . $record->issue;
            }
            $pages = $this->normalizePages($record->pages);
            if ($pages !== '') {
                $bits[] = 'pp. ' . $pages;
            }
            if ($record->year !== '') {
                $bits[] = $record->year;
            }
        } else {
            if ($record->publisher !== '') {
                $bits[] = $record->publisher;
            }
            if ($record->year !== '') {
                $bits[] = $record->year;
            }
        }
        if ($record->doi !== '') {
            $bits[] = 'doi: ' . $record->doi;
        } elseif ($record->url !== '') {
            $bits[] = $record->url;
        }

        $text = $lead;
        if ($bits !== []) {
            $text .= ' ' . implode(', ', $bits);
        }

        return $this->terminate(trim($text));
    }

    public function inText(CitationRecord $record): string
    {
        return '[1]';
    }

    private function bibliographyAuthors(CitationRecord $record): string
    {
        $names = [];
        foreach ($record->authors as $author) {
            $name = $this->ieeeName($author);
            if ($name !== '') {
                $names[] = $name;
            }
        }
        $count = count($names);
        if ($count === 0) {
            return '';
        }
        if ($count > 6) {
            return $names[0] . ' et al.';
        }
        if ($count === 1) {
            return $names[0];
        }
        if ($count === 2) {
            return $names[0] . ' and ' . $names[1];
        }
        $last = array_pop($names);

        return implode(', ', $names) . ', and ' . $last;
    }

    /**
     * @param array{given: string, family: string} $author
     */
    private function ieeeName(array $author): string
    {
        $initials = $this->initials($author['given']);
        $family = trim($author['family']);
        if ($initials === '') {
            return $family;
        }
        if ($family === '') {
            return $initials;
        }

        return $initials . ' ' . $family;
    }
}
