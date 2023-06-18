<?php

namespace App\Model\QueryResult;

use App\Exception\QueryException;
use Google\Cloud\Core\Iterator\ItemIterator;

class MultiLineCoverageQueryResult implements QueryResultInterface
{
    /**
     * @param LineCoverageQueryResult[] $lines
     */
    private function __construct(
        private readonly array $lines,
    ) {
    }

    /**
     * @throws QueryException
     */
    public static function from(ItemIterator|array $results): self
    {
        $lines = [];

        foreach ($results as $result) {
            if (!is_array($result)) {
                continue;
            }

            $lines[] = LineCoverageQueryResult::from($result);
        }

        return new self($lines);
    }

    public function getLines(): array
    {
        return $this->lines;
    }
}
