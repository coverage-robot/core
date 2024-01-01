<?php

namespace App\Query\Result;

use Symfony\Component\Validator\Constraints as Assert;

class TagAvailabilityQueryResult implements QueryResultInterface
{
    /**
     * @param string[] $availableCommits
     */
    public function __construct(
        #[Assert\NotBlank]
        private readonly string $tagName,
        #[Assert\All([
            new Assert\Type(type: 'string'),
            new Assert\NotBlank
        ])]
        private readonly array $availableCommits,
    ) {
    }

    public function getTagName(): string
    {
        return $this->tagName;
    }

    public function getAvailableCommits(): array
    {
        return $this->availableCommits;
    }
}
