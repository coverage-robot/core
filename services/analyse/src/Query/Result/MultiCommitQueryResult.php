<?php

namespace App\Query\Result;

use App\Exception\QueryException;
use Google\Cloud\Core\Iterator\ItemIterator;

class MultiCommitQueryResult implements QueryResultInterface
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
        $files = [];

        foreach ($results as $result) {
            if (!is_array($result)) {
                continue;
            }

            $files[] = CommitQueryResult::from($result);
        }

        return new self($files);
    }

    public function getCommits(): array
    {
        return $this->commits;
    }
}
