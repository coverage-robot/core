<?php

namespace App\Query\Result;

use Symfony\Component\Validator\Constraints as Assert;

final class TagCoverageCollectionQueryResult implements QueryResultInterface
{
    /**
     * @param TagCoverageQueryResult[] $tags
     */
    public function __construct(
        #[Assert\All([
            new Assert\Type(type: TagCoverageQueryResult::class)
        ])]
        private readonly array $tags,
    ) {
    }

    public function getTags(): array
    {
        return $this->tags;
    }
}
