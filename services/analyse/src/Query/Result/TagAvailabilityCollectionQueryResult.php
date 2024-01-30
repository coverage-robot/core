<?php

namespace App\Query\Result;

use OutOfBoundsException;
use Symfony\Component\Validator\Constraints as Assert;

final class TagAvailabilityCollectionQueryResult implements QueryResultInterface
{
    /**
     * @param TagAvailabilityQueryResult[] $tagAvailability
     */
    public function __construct(
        #[Assert\All([
            new Assert\Type(type: TagAvailabilityQueryResult::class)
        ])]
        private readonly array $tagAvailability
    ) {
    }

    /**
     * @throws OutOfBoundsException
     */
    public function getAvailabilityForTagName(string $name): TagAvailabilityQueryResult
    {
        foreach ($this->tagAvailability as $availability) {
            if ($availability->getTagName() === $name) {
                return $availability;
            }
        }

        throw new OutOfBoundsException(sprintf('Tag %s is not available.', $name));
    }

    public function getAvailableTagNames(): array
    {
        return array_map(
            static fn(TagAvailabilityQueryResult $availability): string => $availability->getTagName(),
            $this->tagAvailability
        );
    }
}
