<?php

declare(strict_types=1);

namespace App\Query\Result;

use OutOfBoundsException;
use Override;
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
