<?php

namespace App\Model\QueryResult;

use App\Exception\QueryException;

class CoverageQueryResult implements QueryResultInterface
{
    protected function __construct(
        private readonly float $coveragePercentage,
        private readonly int $lines,
        private readonly int $covered,
        private readonly int $partial,
        private readonly int $uncovered
    ) {
    }

    /**
     * @throws QueryException
     */
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

    public function getCoveragePercentage(): float
    {
        return $this->coveragePercentage;
    }

    public function getLines(): int
    {
        return $this->lines;
    }

    public function getCovered(): int
    {
        return $this->covered;
    }

    public function getPartial(): int
    {
        return $this->partial;
    }

    public function getUncovered(): int
    {
        return $this->uncovered;
    }
}
