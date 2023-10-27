<?php

namespace App\Query\Result;

class TagCoverageCollectionQueryResult implements QueryResultInterface
{
    /**
     * @param TagCoverageQueryResult[] $tags
     */
    public function __construct(
        private readonly array $tags,
    ) {
    }

    public function getTags(): array
    {
        return $this->tags;
    }
}
