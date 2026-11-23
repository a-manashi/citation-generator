<?php

declare(strict_types=1);

namespace App\Libraries\Citation\Formatters;

use App\Libraries\Citation\CitationRecord;

class MlaFormatter extends AbstractCitationFormatter
{
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
                if ($record->edition !== '') {
                    $title .= ' (' . $this->editionNote($record) . ')';
                }
                $parts[] = $this->terminate($title);
            }
        }

        if ($this->isArticle($record)) {
            $bits = [];
            if ($record->journal !== '') {
                $bits[] = $record->journal;
            }
            if ($record->volume !== '') {
                $bits[] = 'vol. ' . $record->volume;
            }
            if ($record->issue !== '') {
                $bits[] = 'no. ' . $record->issue;
            }
            if ($record->year !== '') {
                $bits[] = $record->year;
            }
            $pages = $this->normalizePages($record->pages);
            if ($pages !== '') {
                $bits[] = 'pp. ' . $pages;
            }
            if ($bits !== []) {
                $parts[] = $this->terminate(implode(', ', $bits));
            }
        } else {
            $bits = [];
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
        $families = $this->families($record);
        $count = count($families);
        if ($count === 0) {
            if ($record->title === '') {
                return '';
            }

            return '("' . str_replace('"', "'", $record->title) . '")';
        }
        if ($count === 1) {
            return '(' . $families[0] . ')';
        }
        if ($count === 2) {
            return '(' . $families[0] . ' and ' . $families[1] . ')';
        }

        return '(' . $families[0] . ' et al.)';
    }

    private function bibliographyAuthors(CitationRecord $record): string
    {
        $authors = $record->authors;
        $count = count($authors);
        if ($count === 0) {
            return '';
        }
        if ($count === 1) {
            return $this->person($authors[0], true);
        }
        if ($count === 2) {
            return $this->person($authors[0], true) . ', and ' . $this->person($authors[1], false);
        }

        return $this->person($authors[0], true) . ', et al.';
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
