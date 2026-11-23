<?php

declare(strict_types=1);

namespace App\Libraries\Citation;

use App\Libraries\Citation\Formatters\ApaFormatter;
use App\Libraries\Citation\Formatters\ChicagoFormatter;
use App\Libraries\Citation\Formatters\CitationFormatterInterface;
use App\Libraries\Citation\Formatters\HarvardFormatter;
use App\Libraries\Citation\Formatters\IeeeFormatter;
use App\Libraries\Citation\Formatters\MlaFormatter;
use App\Libraries\Citation\Formatters\VancouverFormatter;

class FormatterFactory
{
    /**
     * @return array<string, string>
     */
    public static function styles(): array
    {
        return [
            'apa'       => 'APA 7th edition',
            'mla'       => 'MLA 9th edition',
            'chicago'   => 'Chicago',
            'harvard'   => 'Harvard',
            'ieee'      => 'IEEE',
            'vancouver' => 'Vancouver',
        ];
    }

    public static function make(string $style): CitationFormatterInterface
    {
        $style = strtolower(trim($style));
        if ($style === '') {
            $style = 'apa';
        }
        if (! isset(self::styles()[$style])) {
            throw new CitationException('Choose a citation style.', CitationException::BAD_RESPONSE);
        }

        return match ($style) {
            'apa'       => new ApaFormatter(),
            'mla'       => new MlaFormatter(),
            'chicago'   => new ChicagoFormatter(),
            'harvard'   => new HarvardFormatter(),
            'ieee'      => new IeeeFormatter(),
            'vancouver' => new VancouverFormatter(),
        };
    }
}
