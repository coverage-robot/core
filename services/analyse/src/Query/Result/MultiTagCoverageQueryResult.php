<?php

namespace App\Query\Result;

use App\Exception\QueryException;
use Google\Cloud\Core\Iterator\ItemIterator;

class MultiTagCoverageQueryResult implements QueryResultInterface
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
    public static function from(ItemIterator|array $results): self
    {
        $tags = [];

        /** @var array $result */
        foreach ($results as $result) {
            $tags[] = TagCoverageQueryResult::from($result);
        }

        return new self($tags);
    }

    public function getTags(): array
    {
        return $this->tags;
    }
}
