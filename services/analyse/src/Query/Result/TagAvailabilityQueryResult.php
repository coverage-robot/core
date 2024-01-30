<?php

namespace App\Query\Result;

use App\Model\CarryforwardTag;
use Symfony\Component\Validator\Constraints as Assert;

final class TagAvailabilityQueryResult implements QueryResultInterface
{
    /**
     * @param CarryforwardTag[] $carryforwardTags
     */
    public function __construct(
        #[Assert\NotBlank]
        private readonly string $tagName,
        #[Assert\All([
            new Assert\Type(type: CarryforwardTag::class)
        ])]
        private readonly array $carryforwardTags,
    ) {
    }

    public function getTagName(): string
    {
        return $this->tagName;
    }

    /**
     * @return CarryforwardTag[]
     */
    public function getCarryforwardTags(): array
    {
        return $this->carryforwardTags;
    }
}
