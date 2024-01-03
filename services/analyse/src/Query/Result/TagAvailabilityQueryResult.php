<?php

namespace App\Query\Result;

use Symfony\Component\Validator\Constraints as Assert;

class TagAvailabilityQueryResult implements QueryResultInterface
{
    /**
     * @param AvailableTagQueryResult[] $availableTags
     */
    public function __construct(
        #[Assert\NotBlank]
        private readonly string $tagName,
        #[Assert\All([
            new Assert\Type(type: AvailableTagQueryResult::class)
        ])]
        private readonly array $availableTags,
    ) {
    }

    public function getTagName(): string
    {
        return $this->tagName;
    }

    /**
     * @return AvailableTagQueryResult[]
     */
    public function getAvailableTags(): array
    {
        return $this->availableTags;
    }
}
