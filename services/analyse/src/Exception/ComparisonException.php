<?php

namespace App\Exception;

use App\Model\ReportWaypoint;
use RuntimeException;

class ComparisonException extends RuntimeException
{
    public static function notComparable(
        ReportWaypoint $base,
        ReportWaypoint $head
    ): ComparisonException {
        return new ComparisonException(
            sprintf(
                'Reports could not be compared as %s at BASE and %s at HEAD are not comparable',
                (string) $base,
                (string) $head
            )
        );
    }
}
