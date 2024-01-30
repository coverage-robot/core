<?php

namespace App\Query\Result;

use Packages\Contracts\Tag\Tag;
use Symfony\Component\Validator\Constraints as Assert;

final class TagCoverageQueryResult implements QueryResultInterface
{
    public function __construct(
        private readonly Tag $tag,
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

    public function getTag(): Tag
    {
        return $this->tag;
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
