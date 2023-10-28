<?php

namespace App\Query\Result;

class TagAvailabilityQueryResult implements QueryResultInterface
{
    /**
     * @param string[] $availableCommits
     */
    public function __construct(
        private readonly string $tagName,
        private readonly array $availableCommits,
    ) {
    }

    public function getTagName(): string
    {
        return $this->tagName;
    }

    public function getAvailableCommits(): array
    {
        return $this->availableCommits;
    }
}
