<?php

declare(strict_types=1);

namespace App\Query\Result;

use App\Model\CarryforwardTag;
use Override;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class TagAvailabilityQueryResult implements QueryResultInterface
{
    /**
     * @param CarryforwardTag[] $carryforwardTags
     */
    public function __construct(
        #[Assert\NotBlank]
        private string $tagName,
        #[Assert\All([
            new Assert\Type(type: CarryforwardTag::class)
        ])]
        private array $carryforwardTags,
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

    #[Override]
    public function getTimeToLive(): int|false
    {
        /**
         * This query can't be cached, as it doesnt use any discernible parameters which will
         * ensure the cached query is still up to date.
         */
        return false;
    }
}
