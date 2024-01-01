<?php

namespace App\Query\Result;

use Symfony\Component\Validator\Constraints as Assert;

class CoverageQueryResult implements QueryResultInterface
{
    public function __construct(
        #[Assert\GreaterThanOrEqual(0)]
        #[Assert\LessThanOrEqual(100)]
        private readonly float $coveragePercentage,
        #[Assert\PositiveOrZero]
        private readonly int $lines,
        #[Assert\PositiveOrZero]
        private readonly int $covered,
        #[Assert\PositiveOrZero]
        private readonly int $partial,
        #[Assert\PositiveOrZero]
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
