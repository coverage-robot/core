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
        if (is_string($result['fileName'] ?? null)) {
            $coverage = parent::from($result);

            return new self(
                (string)$result['fileName'],
                $coverage->getCoveragePercentage(),
                $coverage->getLines(),
                $coverage->getCovered(),
                $coverage->getPartial(),
                $coverage->getUncovered()
            );
        }

        throw QueryException::invalidQueryResult();
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }
}
