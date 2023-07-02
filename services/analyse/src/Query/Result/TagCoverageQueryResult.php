<?php

namespace App\Query\Result;

use App\Exception\QueryException;

class TagCoverageQueryResult extends CoverageQueryResult
{
    private function __construct(
        private readonly string $tag,
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

    /**
     * @throws QueryException
     */
    public static function from(array $result): self
    {
        if (
            is_string($result['tag'] ?? null) &&
            is_numeric($result['coveragePercentage'] ?? null) &&
            is_int($result['lines'] ?? null) &&
            is_int($result['covered'] ?? null) &&
            is_int($result['partial'] ?? null) &&
            is_int($result['uncovered'] ?? null)
        ) {
            return new self(
                (string)$result['tag'],
                (float)$result['coveragePercentage'],
                (int)$result['lines'],
                (int)$result['covered'],
                (int)$result['partial'],
                (int)$result['uncovered']
            );
        }

        throw QueryException::invalidQueryResult();
    }

    public function getTag(): string
    {
        return $this->tag;
    }
}
