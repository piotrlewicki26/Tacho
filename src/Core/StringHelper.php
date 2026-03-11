<?php
declare(strict_types=1);
namespace Core;

/**
 * Shared string-cleaning helper used by controllers that handle binary DDD data.
 */
trait StringHelper
{
    /**
     * Strip non-printable and binary bytes from a string extracted from a DDD file,
     * then trim surrounding whitespace.
     *
     * @param  string $s Raw string from binary DDD data
     * @return string    Cleaned, trimmed string (may be empty)
     */
    private function cleanString(string $s): string
    {
        return trim(preg_replace('/[^\x20-\x7E\xC0-\xFF]/u', '', $s));
    }
}
