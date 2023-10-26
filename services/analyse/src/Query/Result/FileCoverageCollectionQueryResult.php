<?php

namespace App\Query\Result;

use App\Exception\QueryException;
use Google\Cloud\Core\Iterator\ItemIterator;

class FileCoverageCollectionQueryResult implements QueryResultInterface
{
    /**
     * @param FileCoverageQueryResult[] $files
     */
    public function __construct(
        private readonly array $files,
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

            $files[] = FileCoverageQueryResult::from($result);
        }

        return new self($files);
    }

    public function getFiles(): array
    {
        return $this->files;
    }
}
