<?php

namespace App\Client;

use App\Model\OrchestratedEventInterface;
use AsyncAws\DynamoDb\ValueObject\AttributeValue;

interface DynamoDbClientInterface
{
    /**
     * Store an event's state change as a new item in the event store.
     */
    public function storeStateChange(OrchestratedEventInterface $event, int $version, array $change): bool;

    /**
     * Get all of the state changes for a particular event.
     *
     * @return iterable<AttributeValue[]>
     */
    public function getStateChangesForEvent(OrchestratedEventInterface $event): iterable;

    /**
     * Get all of the state changes for all events in a particular repository and commit.
     *
     * @return iterable<AttributeValue[]>
     */
    public function getEventStateChangesForCommit(string $repositoryIdentifier, string $commit): iterable;
}
