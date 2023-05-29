<?php

namespace App\Model\QueryResult;

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
            is_float($result['coveragePercentage'] ?? null) &&
            is_int($result['lines'] ?? null) &&
            is_int($result['covered'] ?? null) &&
            is_int($result['partial'] ?? null) &&
            is_int($result['uncovered'] ?? null)
        ) {
            return new self(
                $result['tag'],
                $result['coveragePercentage'],
                $result['lines'],
                $result['covered'],
                $result['partial'],
                $result['uncovered']
            );
        }

        throw QueryException::invalidQueryResult();
    }

    public function getTag(): string
    {
        return $this->tag;
    }
}