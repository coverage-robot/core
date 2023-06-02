<?php

namespace App\Model\QueryResult;

use App\Exception\QueryException;

class TotalCoverageQueryResult extends CoverageQueryResult
{
    public static function from(array $result): self
    {
        if (
            is_float($result['coveragePercentage'] ?? null) &&
            is_int($result['lines'] ?? null) &&
            is_int($result['covered'] ?? null) &&
            is_int($result['partial'] ?? null) &&
            is_int($result['uncovered'] ?? null)
        ) {
            return new self(
                (float)$result['coveragePercentage'],
                (int)$result['lines'],
                (int)$result['covered'],
                (int)$result['partial'],
                (int)$result['uncovered']
            );
        }

        throw QueryException::invalidQueryResult();
    }
}
