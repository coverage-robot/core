<?php

namespace App\Query\Result;

use Packages\Contracts\Tag\Tag;

class TagCoverageQueryResult extends CoverageQueryResult
{
    public function __construct(
        private readonly Tag $tag,
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

    public function getTag(): Tag
    {
        return $this->tag;
    }
}
