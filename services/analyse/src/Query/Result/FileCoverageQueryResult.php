<?php

namespace App\Query\Result;

class FileCoverageQueryResult extends CoverageQueryResult
{
    public function __construct(
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

    public function getFileName(): string
    {
        return $this->fileName;
    }
}
