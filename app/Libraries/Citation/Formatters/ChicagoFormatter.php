<?php

declare(strict_types=1);

namespace App\Libraries\Citation\Formatters;

use App\Libraries\Citation\CitationRecord;

class ChicagoFormatter extends AbstractCitationFormatter
{
    public function inTextLabel(): string
    {
        return 'Footnote';
    }

    public function bibliography(CitationRecord $record): string
    {
        $parts = [];
        $authors = $this->bibliographyAuthors($record);
        if ($authors !== '') {
            $parts[] = $this->terminate($authors);
        }

        $title = str_replace('"', "'", trim($record->title));
        if ($title !== '') {
            if ($this->isArticle($record)) {
                $parts[] = preg_match('/[.?!]$/u', $title) === 1 ? '"' . $title . '"' : '"' . $title . '."';
            } else {
                $parts[] = $this->terminate($title);
            }
        }

        if ($this->isArticle($record)) {
            $core = $this->journalCore($record);
            if ($core !== '') {
                $parts[] = $this->terminate($core);
            }
        } else {
            $bits = [];
            if ($record->edition !== '') {
                $bits[] = $this->editionNote($record);
            }
            if ($record->publisher !== '') {
                $bits[] = $record->publisher;
            }
            if ($record->year !== '') {
                $bits[] = $record->year;
            }
            if ($bits !== []) {
                $parts[] = $this->terminate(implode(', ', $bits));
            }
        }

        return $this->appendUrl(trim(implode(' ', $parts)), $record);
    }

    public function inText(CitationRecord $record): string
    {
        $who = $this->noteAuthors($record);
        $title = str_replace('"', "'", trim($record->title));
        if ($this->isArticle($record)) {
            $text = $who !== '' ? $who . ', ' : '';
            if ($title !== '') {
                $text .= '"' . $title . '," ';
            }
            $text .= $this->journalCore($record);

            return $this->terminate(trim($text));
        }

        $text = $who;
        if ($title !== '') {
            $text = trim($text . ($text !== '' ? ', ' : '') . $title);
        }
        $bits = [];
        if ($record->publisher !== '') {
            $bits[] = $record->publisher;
        }
        if ($record->year !== '') {
            $bits[] = $record->year;
        }
        if ($bits !== []) {
            $text .= ' (' . implode(', ', $bits) . ')';
        }

        return $this->terminate(trim($text));
    }

    private function bibliographyAuthors(CitationRecord $record): string
    {
        $authors = $record->authors;
        $count = count($authors);
        if ($count === 0) {
            return '';
        }
        if ($count > 10) {
            $authors = array_slice($authors, 0, 7);
            $count = 7;
            $suffix = ', et al.';
        } else {
            $suffix = '';
        }
        if ($count === 1) {
            return $this->person($authors[0], true) . $suffix;
        }

        $names = [$this->person($authors[0], true)];
        for ($i = 1; $i < $count - 1; $i++) {
            $names[] = $this->person($authors[$i], false);
        }
        $last = $this->person($authors[$count - 1], false);

        return implode(', ', $names) . ', and ' . $last . $suffix;
    }

    private function noteAuthors(CitationRecord $record): string
    {
        $authors = $record->authors;
        $count = count($authors);
        if ($count === 0) {
            return '';
        }
        if ($count >= 4) {
            return $this->person($authors[0], false) . ' et al.';
        }
        if ($count === 1) {
            return $this->person($authors[0], false);
        }
        if ($count === 2) {
            return $this->person($authors[0], false) . ' and ' . $this->person($authors[1], false);
        }

        return $this->person($authors[0], false) . ', ' . $this->person($authors[1], false) . ', and ' . $this->person($authors[2], false);
    }

    private function journalCore(CitationRecord $record): string
    {
        $core = $record->journal;
        if ($record->volume !== '') {
            $core .= ($core !== '' ? ' ' : '') . $record->volume;
        }
        if ($record->issue !== '') {
            $core .= ', no. ' . $record->issue;
        }
        if ($record->year !== '') {
            $core .= ' (' . $record->year . ')';
        }
        $pages = $this->normalizePages($record->pages);
        if ($pages !== '') {
            $core .= ': ' . $pages;
        }

        return trim($core);
    }

    /**
     * @param array{given: string, family: string} $author
     */
    private function person(array $author, bool $invert): string
    {
        $given = trim($author['given']);
        $family = trim($author['family']);
        if ($given === '') {
            return $family;
        }
        if ($family === '') {
            return $given;
        }

        return $invert ? $family . ', ' . $given : $given . ' ' . $family;
    }
}
