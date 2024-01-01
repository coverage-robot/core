<?php

namespace App\Query\Result;

use Symfony\Component\Validator\Constraints as Assert;

class FileCoverageQueryResult extends CoverageQueryResult
{
    public function __construct(
        #[Assert\NotBlank]
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
