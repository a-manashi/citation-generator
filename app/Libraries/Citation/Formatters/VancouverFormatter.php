<?php

declare(strict_types=1);

namespace App\Libraries\Citation\Formatters;

use App\Libraries\Citation\CitationRecord;

class VancouverFormatter extends AbstractCitationFormatter
{
    public function bibliography(CitationRecord $record): string
    {
        $parts = [];
        $authors = $this->bibliographyAuthors($record);
        if ($authors !== '') {
            $parts[] = $this->terminate($authors);
        }
        if ($record->title !== '') {
            $parts[] = $this->terminate($record->title);
        }

        if ($this->isArticle($record)) {
            if ($record->journal !== '') {
                $parts[] = $this->terminate($record->journal);
            }
            $cite = '';
            if ($record->year !== '') {
                $cite .= $record->year;
            }
            $volume = $this->volumeIssue($record);
            if ($volume !== '') {
                $cite .= ($record->year !== '' ? ';' : '') . $volume;
            }
            $pages = $this->normalizePages($record->pages);
            if ($pages !== '') {
                $cite .= ($cite !== '' ? ':' : '') . $pages;
            }
            if ($cite !== '') {
                $parts[] = $this->terminate($cite);
            }
        } elseif ($record->publisher !== '' && $record->year !== '') {
            $parts[] = $this->terminate($record->publisher . '; ' . $record->year);
        } elseif ($record->publisher !== '') {
            $parts[] = $this->terminate($record->publisher);
        } elseif ($record->year !== '') {
            $parts[] = $this->terminate($record->year);
        }

        $text = trim(implode(' ', $parts));
        if ($record->doi !== '') {
            if ($text !== '' && ! str_ends_with($text, '.')) {
                $text .= '.';
            }
            $text = trim($text . ' doi:' . $record->doi);
        } elseif ($record->url !== '') {
            $text = trim($text . ' ' . $record->url);
        }

        return $text;
    }

    public function inText(CitationRecord $record): string
    {
        return '(1)';
    }

    private function bibliographyAuthors(CitationRecord $record): string
    {
        $names = [];
        foreach ($record->authors as $author) {
            $initials = $this->compactInitials($author['given']);
            $family = trim($author['family']);
            $name = trim($family . ($initials !== '' ? ' ' . $initials : ''));
            if ($name !== '') {
                $names[] = $name;
            }
        }
        $count = count($names);
        if ($count === 0) {
            return '';
        }
        if ($count > 6) {
            return implode(', ', array_slice($names, 0, 6)) . ', et al.';
        }

        return implode(', ', $names);
    }
}
