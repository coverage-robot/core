<?php

namespace App\Query\Result;

use Symfony\Component\Validator\Constraints as Assert;

final class FileCoverageQueryResult implements QueryResultInterface
{
    public function __construct(
        #[Assert\NotBlank]
        private readonly string $fileName,
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

    public function getFileName(): string
    {
        return $this->fileName;
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
