<?php

namespace App\Query\Result;

class FileCoverageCollectionQueryResult implements QueryResultInterface
{
    /**
     * @param FileCoverageQueryResult[] $files
     */
    public function __construct(
        private readonly array $files,
    ) {
    }

    public function getFiles(): array
    {
        return $this->files;
    }
}
