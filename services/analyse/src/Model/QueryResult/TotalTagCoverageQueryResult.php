<?php

namespace App\Model\QueryResult;

use App\Exception\QueryException;
use Google\Cloud\Core\Iterator\ItemIterator;

class TotalTagCoverageQueryResult implements QueryResultInterface
{
    /**
     * @param TagCoverageQueryResult[] $tags
     */
    private function __construct(
        private readonly array $tags,
    ) {
    }

    /**
     * @throws QueryException
     */
    public static function from(ItemIterator $results): self
    {
        $lines = [];

        foreach ($results as $result) {
            $lines[] = TagCoverageQueryResult::from($result);
        }

        return new self($lines);
    }

    public function getTags(): array
    {
        return $this->tags;
    }
}
