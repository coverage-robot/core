<?php

namespace App\Query\Result;

class CommitCollectionQueryResult implements QueryResultInterface
{
    /**
     * @param CommitQueryResult[] $commits
     */
    public function __construct(
        private readonly array $commits,
    ) {
    }

    /**
     * @return CommitQueryResult[]
     */
    public function getCommits(): array
    {
        return $this->commits;
    }
}
