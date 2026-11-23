<?php

declare(strict_types=1);

namespace App\Libraries\Citation\Formatters;

use App\Libraries\Citation\CitationRecord;

class HarvardFormatter extends AbstractCitationFormatter
{
    public function bibliography(CitationRecord $record): string
    {
        $authors = $this->bibliographyAuthors($record);
        $year = $record->year !== '' ? $record->year : 'n.d.';
        $body = $authors !== '' ? $authors . ' (' . $year . ')' : '(' . $year . ')';
        $title = str_replace(["'", '"'], ['’', "'"], trim($record->title));

        if ($this->isArticle($record)) {
            if ($title !== '') {
                $body .= " '" . $title . "'";
            }
            $bits = [];
            if ($record->journal !== '') {
                $bits[] = $record->journal;
            }
            $volume = $this->volumeIssue($record);
            if ($volume !== '') {
                $bits[] = $volume;
            }
            $pages = $this->normalizePages($record->pages);
            if ($pages !== '') {
                $bits[] = 'pp. ' . $pages;
            }
            if ($bits !== []) {
                $body .= ', ' . implode(', ', $bits);
            }
            $body = $this->terminate($body);
            if ($record->doi !== '') {
                $body .= ' doi:' . $record->doi;
            } elseif ($record->url !== '') {
                $body .= ' ' . $record->url;
            }

            return trim($body);
        }

        if ($title !== '') {
            if ($record->edition !== '') {
                $title .= ' (' . $this->editionNote($record) . ')';
            }
            $body .= ' ' . $this->terminate($title);
        }
        if ($record->publisher !== '') {
            $body .= ' ' . $this->terminate($record->publisher);
        } else {
            $body = $this->terminate($body);
        }
        if ($record->doi !== '') {
            $body = $this->terminate(rtrim($body, '.')) . ' doi:' . $record->doi;
        }

        return trim($body);
    }

    public function inText(CitationRecord $record): string
    {
        $year = $record->year !== '' ? $record->year : 'n.d.';
        $families = $this->families($record);
        $count = count($families);
        if ($count === 0) {
            if ($record->title === '') {
                return '(' . $year . ')';
            }

            return '(\'' . str_replace("'", '’', $record->title) . '\', ' . $year . ')';
        }
        if ($count === 1) {
            return '(' . $families[0] . ', ' . $year . ')';
        }
        if ($count === 2) {
            return '(' . $families[0] . ' and ' . $families[1] . ', ' . $year . ')';
        }

        return '(' . $families[0] . ' et al., ' . $year . ')';
    }

    private function bibliographyAuthors(CitationRecord $record): string
    {
        $names = [];
        foreach ($record->authors as $author) {
            $family = trim($author['family']);
            $initials = $this->initials($author['given']);
            if ($family === '' && $initials === '') {
                continue;
            }
            $names[] = $initials === '' ? $family : $family . ', ' . $initials;
        }
        $count = count($names);
        if ($count === 0) {
            return '';
        }
        if ($count > 20) {
            $names = array_slice($names, 0, 19);
            $names[] = 'et al.';

            return implode(', ', $names);
        }
        if ($count === 1) {
            return $names[0];
        }
        $last = array_pop($names);

        return implode(', ', $names) . ' and ' . $last;
    }
}
