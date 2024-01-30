<?php

namespace App\Query\Result;

use Symfony\Component\Validator\Constraints as Assert;

final class FileCoverageCollectionQueryResult implements QueryResultInterface
{
    /**
     * @param FileCoverageQueryResult[] $files
     */
    public function __construct(
        #[Assert\All([
            new Assert\Type(type: FileCoverageQueryResult::class)
        ])]
        private readonly array $files,
    ) {
    }

    public function getFiles(): array
    {
        return $this->files;
    }
}
