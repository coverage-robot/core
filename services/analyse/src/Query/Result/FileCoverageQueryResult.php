<?php

declare(strict_types=1);

namespace App\Query\Result;

use Override;
use Symfony\Component\Validator\Constraints as Assert;

final class FileCoverageQueryResult implements QueryResultInterface
{
    public function __construct(
        #[Assert\NotBlank]
        private readonly string $fileName,
        #[Assert\GreaterThanOrEqual(0)]
        #[Assert\LessThanOrEqual(100)]
        private readonly float $coveragePercentage,
        #[Assert\All([
            new Assert\Type('int'),
            new Assert\Positive(),
        ])]
        private readonly array $lines,
        #[Assert\All([
            new Assert\Type('int'),
            new Assert\Positive(),
        ])]
        private readonly array $coveredLines,
        #[Assert\All([
            new Assert\Type('int'),
            new Assert\Positive(),
        ])]
        private readonly array $partialLines,
        #[Assert\All([
            new Assert\Type('int'),
            new Assert\Positive(),
        ])]
        private readonly array $uncoveredLines,
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

    public function getLines(): array
    {
        return $this->lines;
    }

    public function getCoveredLines(): array
    {
        return $this->coveredLines;
    }

    public function getPartialLines(): array
    {
        return $this->partialLines;
    }

    public function getUncoveredLines(): array
    {
        return $this->uncoveredLines;
    }

    #[Override]
    public function getTimeToLive(): int|false
    {
        return self::DEFAULT_QUERY_CACHE_TTL;
    }
}
