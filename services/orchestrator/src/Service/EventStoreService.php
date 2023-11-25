<?php

namespace App\Service;

use App\Client\DynamoDbClient;
use App\Exception\EventStoreException;
use App\Model\EventStateChange;
use App\Model\EventStateChangeCollection;
use App\Model\OrchestratedEventInterface;
use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\DynamoDb\Exception\ConditionalCheckFailedException;
use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use DateTimeImmutable;
use InvalidArgumentException;
use Packages\Contracts\Provider\Provider;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class EventStoreService implements EventStoreServiceInterface
{
    /**
     * @param SerializerInterface&NormalizerInterface&DenormalizerInterface $serializer
     */
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly DynamoDbClient $dynamoDbClient,
        private readonly LoggerInterface $eventStoreLogger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getStateChangesBetweenEvent(
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
    public function reduceStateChangesToEvent(EventStateChangeCollection $stateChanges): OrchestratedEventInterface|null
    {
        $latestKnownState = array_reduce(
            $stateChanges->getEvents(),
            static fn (array $finalState, EventStateChange $stateChange) => array_merge(
                $finalState,
                $stateChange->getEvent()
            ),
            []
        );

        if ($latestKnownState === []) {
            // This is some kind of collection with no events to reduce, so we're safe to say we won't
            // be able to make an event out of the arguments passed.
            return null;
        }

        try {
            return $this->serializer->denormalize(
                $latestKnownState,
                OrchestratedEventInterface::class
            );
        } catch (ExceptionInterface $exception) {
            $this->eventStoreLogger->error(
                'Failed to denormalize event from state changes, returning null instead.',
                [
                    'stateChanges' => $stateChanges,
                    'exception' => $exception
                ]
            );

            return null;
        }
    }

    /**
     * Store any state changes which have occurred between the current state, and whatever the event store believed
     * the state of the event to be in prior to this call.
     */
    public function storeStateChange(OrchestratedEventInterface $event): EventStateChange|false
    {
        $existingStateChanges = $this->getAllStateChangesForEvent($event);

        $previousState = $this->reduceStateChangesToEvent($existingStateChanges);

        $diff = $this->getStateChangesBetweenEvent($previousState, $event);

        if ($diff === []) {
            $this->eventStoreLogger->info(
                sprintf(
                    'No change detected in event state for %s.',
                    (string)$event
                ),
                [
                    'event' => $event::class,
                    'previousState' => $previousState,
                    'diff' => $diff
                ]
            );

            return new EventStateChange(
                $event->getProvider(),
                $event->getUniqueIdentifier(),
                $event->getOwner(),
                $event->getRepository(),
                1,
                $diff
            );
        }

        try {
            $version = count($existingStateChanges) + 1;

            $successful = $this->dynamoDbClient->storeStateChange($event, $version, $diff);

            if (!$successful) {
                return false;
            }

            return new EventStateChange(
                $event->getProvider(),
                $event->getUniqueIdentifier(),
                $event->getOwner(),
                $event->getRepository(),
                $version,
                $diff
            );
        } catch (ConditionalCheckFailedException $exception) {
            $this->eventStoreLogger->info(
                'State change with version number for event already exists.',
                [
                    'event' => (string)$event,
                    'version' => $version,
                    'diff' => $diff,
                    'exception' => $exception
                ]
            );

            throw $exception;
        } catch (HttpException $exception) {
            $this->eventStoreLogger->error(
                'Failed to put event into store.',
                [
                    'event' => (string)$event,
                    'exception' => $exception
                ]
            );

            return false;
        }
    }

    /**
     * Get a collection of all the state changes which have occurred for a given commit.
     *
     * @return EventStateChangeCollection[]
     */
    public function getAllStateChangesForCommit(string $repositoryIdentifier, string $commit): array
    {
        try {
            $items = $this->dynamoDbClient->getEventStateChangesForCommit($repositoryIdentifier, $commit);

            /** @var EventStateChangeCollection[] $stateChanges */
            $stateChanges = [];
            foreach ($items as $item) {
                $stateChange = $this->parseEventStateChange($item);

                if (!array_key_exists($stateChange->getIdentifier(), $stateChanges)) {
                    $stateChanges[$stateChange->getIdentifier()] = new EventStateChangeCollection([]);
                }

                $stateChanges[$stateChange->getIdentifier()]->setStateChange($stateChange);
            }

            return $stateChanges;
        } catch (HttpException $exception) {
            $this->eventStoreLogger->error(
                'Failed to retrieve changes for identifier.',
                [
                    'identifier' => $repositoryIdentifier,
                    'exception' => $exception
                ]
            );

            throw new EventStoreException('Failed to retrieve changes for identifier.', 0, $exception);
        }
    }

    /**
     * Get a collection of all the state changes which have occurred for a given event.
     *
     * This effectively returns a history of all the changes which have occurred for a given event, which can be used
     * to reconstruct an event at any point in time.
     */
    public function getAllStateChangesForEvent(OrchestratedEventInterface $event): EventStateChangeCollection
    {
        try {
            $items = $this->dynamoDbClient->getStateChangesForEvent($event);

            $stateChanges = new EventStateChangeCollection([]);
            foreach ($items as $item) {
                $stateChanges->setStateChange($this->parseEventStateChange($item));
            }

            return $stateChanges;
        } catch (HttpException $exception) {
            $this->eventStoreLogger->error(
                'Failed to retrieve changes for identifier.',
                [
                    'identifier' => (string)$event,
                    'exception' => $exception
                ]
            );

            throw new EventStoreException('Failed to retrieve changes for identifier.', 0, $exception);
        }
    }

    /**
     * Parse a single state change item from DynamoDB into a state change object which
     * can be used in collections and for reducing into an event.
     *
     * @param AttributeValue[] $item
     */
    private function parseEventStateChange(array $item): EventStateChange
    {
        return new EventStateChange(
            Provider::from((string)$item['provider']->getS()),
            (string)(isset($item['identifier']) ? $item['identifier']->getS() : ''),
            (string)(isset($item['owner']) ? $item['owner']->getS() : ''),
            (string)(isset($item['repository']) ? $item['repository']->getS() : ''),
            (int)(isset($item['version']) ? $item['version']->getN() : 1),
            (array)$this->serializer->decode(
                (string)(isset($item['event']) ? $item['event']->getS() : '[]'),
                'json'
            ),
            isset($item['eventTime']) ?
                DateTimeImmutable::createFromFormat('U', $item['eventTime']->getN())
                : null
        );
    }
}
