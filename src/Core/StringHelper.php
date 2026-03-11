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
        // No /u flag: DDD files use Latin-1/binary encoding, not UTF-8.
        // Using /u would cause preg_replace() to return null on non-UTF-8 bytes.
        return trim(preg_replace('/[^\x20-\x7E\xC0-\xFF]/', '', $s));
    }
}
