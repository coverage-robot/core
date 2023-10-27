<?php

namespace App\Query\Result;

class CoverageQueryResult implements QueryResultInterface
{
    public function __construct(
        private readonly float $coveragePercentage,
        private readonly int $lines,
        private readonly int $covered,
        private readonly int $partial,
        private readonly int $uncovered
    ) {
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
