<?php

declare(strict_types=1);

namespace App\Libraries\Citation\Formatters;

use App\Libraries\Citation\CitationRecord;

interface CitationFormatterInterface
{
    public function bibliography(CitationRecord $record): string;

    public function inText(CitationRecord $record): string;

    public function inTextLabel(): string;

    /**
     * @return list<string>
     */
    public function warnings(CitationRecord $record): array;
}
