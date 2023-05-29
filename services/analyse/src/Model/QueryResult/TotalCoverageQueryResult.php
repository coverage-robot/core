<?php

namespace App\Model\QueryResult;

class TotalCoverageQueryResult extends CoverageQueryResult
{
    public static function from(array $result): self
    {
        return new self(
            $result['coveragePercentage'],
            $result['lines'],
            $result['covered'],
            $result['partial'],
            $result['uncovered']
        );
    }
}
