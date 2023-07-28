<?php

namespace App\Query\Result;

use App\Exception\QueryException;
use Google\Cloud\Core\Iterator\ItemIterator;

class CommitCollectionQueryResult implements QueryResultInterface
{
    /**
     * @param CommitQueryResult[] $commits
     */
    private function __construct(
        private readonly array $commits,
    ) {
    }

    /**
     * @throws QueryException
     */
    public static function from(ItemIterator|array $results): self
    {
        $commits = [];

        foreach ($results as $row) {
            if (!is_array($row)) {
                continue;
            }

            $commits[] = CommitQueryResult::from($row);
        }

        return new self($commits);
    }

    public function getCommits(): array
    {
        return $this->commits;
    }
}
