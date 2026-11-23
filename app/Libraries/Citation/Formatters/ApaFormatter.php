<?php

declare(strict_types=1);

namespace App\Libraries\Citation\Formatters;

use App\Libraries\Citation\CitationRecord;

class ApaFormatter extends AbstractCitationFormatter
{
    public function bibliography(CitationRecord $record): string
    {
        $authors = $this->bibliographyAuthors($record);
        $year = $record->year !== '' ? $record->year : 'n.d.';
        $parts = [];

        if ($authors !== '') {
            $parts[] = $authors . ' (' . $year . ').';
        }

        $title = trim($record->title);
        if ($title !== '' && ! $this->isArticle($record) && $record->edition !== '') {
            $title .= ' (' . $this->editionNote($record) . ')';
        }
        if ($title !== '') {
            $title = $this->terminate($title);
            $parts[] = $authors === '' ? $title . ' (' . $year . ').' : $title;
        } elseif ($authors === '') {
            $parts[] = '(' . $year . ').';
        }

        if ($this->isArticle($record)) {
            $tail = $this->articleTail($record);
            if ($tail !== '') {
                $parts[] = $tail;
            }
        } elseif ($record->publisher !== '') {
            $parts[] = $this->terminate($record->publisher);
        }

        return $this->appendUrl(trim(implode(' ', $parts)), $record);
    }

    public function inText(CitationRecord $record): string
    {
        $year = $record->year !== '' ? $record->year : 'n.d.';
        $who = $this->inTextAuthors($record);
        if ($who === '') {
            if ($record->title === '') {
                return '(' . $year . ')';
            }

            return '("' . str_replace('"', "'", $record->title) . '", ' . $year . ')';
        }

        return '(' . $who . ', ' . $year . ')';
    }

    private function bibliographyAuthors(CitationRecord $record): string
    {
        $formatted = [];
        foreach ($record->authors as $author) {
            $name = $this->apaName($author);
            if ($name !== '') {
                $formatted[] = $name;
            }
        }
        $count = count($formatted);
        if ($count === 0) {
            return '';
        }
        if ($count === 1) {
            return $formatted[0];
        }
        if ($count === 2) {
            return $formatted[0] . ', & ' . $formatted[1];
        }
        if ($count <= 20) {
            $last = array_pop($formatted);

            return implode(', ', $formatted) . ', & ' . $last;
        }

        $head = array_slice($formatted, 0, 19);

        return implode(', ', $head) . ', ... ' . $formatted[$count - 1];
    }

    private function inTextAuthors(CitationRecord $record): string
    {
        $families = $this->families($record);
        $count = count($families);
        if ($count === 0) {
            return '';
        }
        if ($count === 1) {
            return $families[0];
        }
        if ($count === 2) {
            return $families[0] . ' & ' . $families[1];
        }

        return $families[0] . ' et al.';
    }

    /**
     * @param array{given: string, family: string} $author
     */
    private function apaName(array $author): string
    {
        $family = trim($author['family']);
        $initials = $this->initials($author['given']);
        if ($family === '') {
            return $initials;
        }
        if ($initials === '') {
            return $family;
        }

        return $family . ', ' . $initials;
    }

    private function articleTail(CitationRecord $record): string
    {
        $extra = [];
        $volume = $this->volumeIssue($record);
        if ($volume !== '') {
            $extra[] = $volume;
        }
        $pages = $this->normalizePages($record->pages);
        if ($pages !== '') {
            $extra[] = $pages;
        }
        if ($record->journal === '' && $extra === []) {
            return '';
        }
        $text = $record->journal;
        if ($text === '') {
            $text = implode(', ', $extra);
        } elseif ($extra !== []) {
            $text .= ', ' . implode(', ', $extra);
        }

        return $this->terminate($text);
    }
}
