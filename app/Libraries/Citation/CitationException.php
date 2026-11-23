<?php

declare(strict_types=1);

namespace App\Libraries\Citation;

use RuntimeException;

class CitationException extends RuntimeException
{
    public const NOT_FOUND = 404;

    public const SKIP = 422;

    public const BAD_RESPONSE = 502;

    public const UNAVAILABLE = 503;

    public const RATE_LIMIT = 429;

    public function __construct(string $publicMessage, int $code = self::UNAVAILABLE)
    {
        parent::__construct($publicMessage, $code);
    }
}
