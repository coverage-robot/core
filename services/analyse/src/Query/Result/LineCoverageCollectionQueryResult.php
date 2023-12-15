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

    /**
     * @return LineCoverageQueryResult[]
     */
    public function getLines(): array
    {
        return $this->lines;
    }
}
