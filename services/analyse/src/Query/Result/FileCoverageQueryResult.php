<?php

namespace App\Query\Result;

use App\Exception\QueryException;

class FileCoverageQueryResult extends CoverageQueryResult
{
    private function __construct(
        private readonly string $fileName,
        readonly float $coveragePercentage,
        readonly int $lines,
        readonly int $covered,
        readonly int $partial,
        readonly int $uncovered
    ) {
        parent::__construct(
            $coveragePercentage,
            $lines,
            $covered,
            $partial,
            $uncovered
        );
    }

    public static function from(array $result): self
    {
        if (
            is_string($result['fileName'] ?? null) &&
            is_numeric($result['coveragePercentage'] ?? null) &&
            is_int($result['lines'] ?? null) &&
            is_int($result['covered'] ?? null) &&
            is_int($result['partial'] ?? null) &&
            is_int($result['uncovered'] ?? null)
        ) {
            return new self(
                (string)$result['fileName'],
                (float)$result['coveragePercentage'],
                (int)$result['lines'],
                (int)$result['covered'],
                (int)$result['partial'],
                (int)$result['uncovered']
            );
        }

        throw QueryException::invalidQueryResult();
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }
}
