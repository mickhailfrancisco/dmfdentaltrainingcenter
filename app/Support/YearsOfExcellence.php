<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * DMF started operating February 1, 2017. The "years of excellence" count
 * on the landing page increments once a year, exactly on February 1.
 *
 * @author CKD
 *
 * @created 2026-08-26
 */
final class YearsOfExcellence
{
    private const START_YEAR = 2017;

    private const ANNIVERSARY_MONTH = 2;

    public static function asOf(CarbonInterface $now): int
    {
        $effectiveYear = $now->month >= self::ANNIVERSARY_MONTH
            ? $now->year
            : $now->year - 1;

        return $effectiveYear - self::START_YEAR + 1;
    }
}
