<?php

namespace App\Service;

use App\Model\OrchestratedEventInterface;
use InvalidArgumentException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class EventStoreService
{
    /**
     * @param SerializerInterface&NormalizerInterface&DenormalizerInterface $serializer
     */
    public function __construct(
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * Get the state change between our last known state, and the new
     * state of an event.
     */
    public function getStateChange(
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
     * Reduce a pre-existing set of state changes into a single event, representing the current
     * state of the event.
     * 
     * @throws ExceptionInterface
     */
    public function reduceStateChanges(array $stateChanges): OrchestratedEventInterface
    {
        $reducingEvent = null;
        $latestKnownState = [];

        foreach ($stateChanges as $stateChange) {
            if (
                $reducingEvent &&
                $reducingEvent !== $stateChange['type']
            ) {
                continue;
            }

            $reducingEvent = $stateChange['type'];

            $latestKnownState = array_merge(
                $latestKnownState,
                $stateChange
            );
        }

        return $this->serializer->denormalize(
            $latestKnownState,
            OrchestratedEventInterface::class
        );
    }
}
