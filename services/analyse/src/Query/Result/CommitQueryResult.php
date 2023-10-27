<?php

namespace App\Query\Result;

use Packages\Models\Model\Tag;

class CommitQueryResult implements QueryResultInterface
{
    /**
     * @param string $commit
     * @param Tag[] $tags
     */
    public function __construct(
        private readonly string $commit,
        private readonly array $tags
    ) {
    }

    public function getCommit(): string
    {
        return $this->commit;
    }

    public function getTags(): array
    {
        return $this->tags;
    }
}
