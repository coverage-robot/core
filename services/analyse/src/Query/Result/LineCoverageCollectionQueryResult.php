<?php

namespace App\Query\Result;

class LineCoverageCollectionQueryResult implements QueryResultInterface
{
    /**
     * @param LineCoverageQueryResult[] $lines
     */
    public function __construct(
        private readonly array $lines,
    ) {
    }

    public function getLines(): array
    {
        return $this->lines;
    }
}
