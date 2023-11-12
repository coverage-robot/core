<?php

namespace App\Service;

use App\Model\EventStateChange;
use App\Model\EventStateChangeCollection;
use App\Model\OrchestratedEventInterface;
use InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class EventStoreService implements EventStoreServiceInterface
{
    /**
     * @param SerializerInterface&NormalizerInterface&DenormalizerInterface $serializer
     */
    public function __construct(
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getStateChangeForEvent(
        ?OrchestratedEventInterface $currentState,
        OrchestratedEventInterface $newState
    ): array {
        if (!$currentState) {
            return (array)$this->serializer->normalize($newState);
        }

        if (get_class($currentState) !== get_class($newState)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Events must be of the same type. %s and %s provided.',
                    get_class($currentState),
                    get_class($newState)
                )
            );
        }

        $currentState = (array)$this->serializer->normalize($currentState);
        $newState = (array)$this->serializer->normalize($newState);

        return array_diff(
            $newState,
            $currentState
        );
    }

    /**
     * @inheritDoc
     */
    public function reduceStateChangesToEvent(EventStateChangeCollection $stateChanges): OrchestratedEventInterface
    {
        $latestKnownState = array_reduce(
            $stateChanges->getEvents(),
            static fn (array $finalState, EventStateChange $stateChange) => array_merge(
                $finalState,
                $stateChange->getEvent()
            ),
            []
        );

        return $this->serializer->denormalize(
            $latestKnownState,
            OrchestratedEventInterface::class
        );
    }
}
