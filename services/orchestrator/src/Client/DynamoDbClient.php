<?php

namespace App\Client;

use App\Enum\EnvironmentVariable;
use App\Model\EventStateChange;
use App\Model\EventStateChangeCollection;
use App\Model\OrchestratedEventInterface;
use App\Service\EnvironmentService;
use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\DynamoDb\Enum\ComparisonOperator;
use AsyncAws\DynamoDb\Enum\ReturnValuesOnConditionCheckFailure;
use AsyncAws\DynamoDb\Enum\Select;
use AsyncAws\DynamoDb\Exception\ConditionalCheckFailedException;
use AsyncAws\DynamoDb\Input\PutItemInput;
use AsyncAws\DynamoDb\Input\QueryInput;
use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use Packages\Models\Enum\Provider;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class DynamoDbClient
{
    /**
     * The default TTL for each event in the store, in seconds - currently 12 hours.
     */
    private const DEFAULT_EVENT_TTL = 43200;

    private const REPOSITORY_IDENTIFIER_COLUMN = 'repositoryIdentifier';

    private const COMMIT_COLUMN = 'commit';

    /**
     * The name of the index used to query for all of the events for a particular
     * repository and commit.
     */
    private const REPOSITORY_COMMIT_INDEX = self:: REPOSITORY_IDENTIFIER_COLUMN . '-' . self::COMMIT_COLUMN . '-index';

    /**
     * @param SerializerInterface&DecoderInterface&DenormalizerInterface $serializer
     */
    public function __construct(
        private readonly \AsyncAws\DynamoDb\DynamoDbClient $dynamoDbClient,
        private readonly EnvironmentService $environmentService,
        private readonly SerializerInterface $serializer,
        private readonly LoggerInterface $dynamoDbClientLogger
    ) {
    }

    /**
     * Store an event's state change as a new item in the event store.
     *
     * @throws ConditionalCheckFailedException
     */
    public function storeStateChange(OrchestratedEventInterface $event, int $version, array $change): bool
    {
        try {
            $response = $this->dynamoDbClient->putItem(
                new PutItemInput(
                    [
                        'TableName' => $this->environmentService->getVariable(EnvironmentVariable::EVENT_STORE),
                        'ConditionExpression' => 'attribute_not_exists(version)',
                        'ReturnValuesOnConditionCheckFailure' => ReturnValuesOnConditionCheckFailure::ALL_OLD,
                        'Item' => [
                            'identifier' => [
                                'S' => $event->getUniqueIdentifier(),
                            ],
                            self::REPOSITORY_IDENTIFIER_COLUMN => [
                                'S' => $event->getUniqueRepositoryIdentifier(),
                            ],
                            'provider' => [
                                'S' => $event->getProvider()->value
                            ],
                            'owner' => [
                                'S' => $event->getOwner()
                            ],
                            'repository' => [
                                'S' => $event->getRepository()
                            ],
                            self::COMMIT_COLUMN => [
                                'S' => $event->getCommit()
                            ],
                            'version' => [
                                'N' => (string)$version
                            ],
                            'event' => [
                                'S' => $this->serializer->serialize($change, 'json')
                            ],
                            'expiry' => [
                                'N' => (string)(time() + self::DEFAULT_EVENT_TTL)
                            ],
                        ],
                    ]
                )
            );

            $response->resolve();
        } catch (ConditionalCheckFailedException $exception) {
            $this->dynamoDbClientLogger->info(
                'State change with version number for event already exists.',
                [
                    'event' => (string)$event,
                    'version' => $version,
                    'change' => $change,
                    'exception' => $exception
                ]
            );

            throw $exception;
        } catch (HttpException $exception) {
            $this->dynamoDbClientLogger->error(
                'Failed to put event into store.',
                [
                    'event' => $event,
                    'exception' => $exception
                ]
            );

            return false;
        }

        return true;
    }

    /**
     * Get all of the state changes for a particular event.
     *
     * @throws RuntimeException
     */
    public function getStateChangesForEvent(OrchestratedEventInterface $event): EventStateChangeCollection
    {
        try {
            $response = $this->dynamoDbClient->query(
                new QueryInput(
                    [
                        'TableName' => $this->environmentService->getVariable(
                            EnvironmentVariable::EVENT_STORE
                        ),
                        'Select' => Select::ALL_ATTRIBUTES,
                        /**
                         * Consistent reads are required to ensure we get the latest version of the event, rather
                         * than an eventually consistent set of query results.
                         */
                        'ConsistentRead' => true,
                        /**
                         * For these types of model queries, we only ever need to look over the identifier primary
                         * key to find all of the recorded event changes.
                         */
                        'KeyConditions' => [
                            'identifier' => [
                                'AttributeValueList' => [
                                    [
                                        'S' => $event->getUniqueIdentifier()
                                    ]
                                ],
                                'ComparisonOperator' => ComparisonOperator::EQ
                            ],
                        ],
                    ]
                )
            );

            $response->resolve();
        } catch (HttpException $exception) {
            $this->dynamoDbClientLogger->error(
                'Failed to retrieve changes for identifier.',
                [
                    'identifier' => (string)$event,
                    'exception' => $exception
                ]
            );

            throw new RuntimeException('Failed to retrieve changes for identifier.', 0, $exception);
        }

        $stateChanges = new EventStateChangeCollection([]);
        foreach ($response->getItems() as $item) {
            $stateChanges->setStateChange(
                $this->parseEventStateChange($item)
            );
        }

        return $stateChanges;
    }

    /**
     * Get all of the state changes for all events in a particular repository and commit.
     *
     * @param OrchestratedEventInterface $event
     * @return EventStateChangeCollection[]
     */
    public function getEventStateChangesForCommit(OrchestratedEventInterface $event): array
    {
        try {
            $response = $this->dynamoDbClient->query(
                new QueryInput(
                    [
                        'TableName' => $this->environmentService->getVariable(
                            EnvironmentVariable::EVENT_STORE
                        ),
                        'Select' => Select::ALL_ATTRIBUTES,
                        /**
                         * Use the index to query for all of the events for a particular repository and
                         * commit. THis he,ps us optimise query performance across the event store.
                         */
                        'IndexName' => self::REPOSITORY_COMMIT_INDEX,
                        /**
                         * For these types of queries, we can use the repository identifier, and the commit hash in
                         * order to return _all_ of the event state changes in a particular commit.
                         */
                        'KeyConditions' => [
                            self::REPOSITORY_IDENTIFIER_COLUMN => [
                                'AttributeValueList' => [
                                    [
                                        'S' => $event->getUniqueRepositoryIdentifier()
                                    ]
                                ],
                                'ComparisonOperator' => ComparisonOperator::EQ
                            ],
                            self::COMMIT_COLUMN => [
                                'AttributeValueList' => [
                                    [
                                        'S' => $event->getCommit()
                                    ]
                                ],
                                'ComparisonOperator' => ComparisonOperator::EQ
                            ],
                        ],
                    ]
                )
            );

            $response->resolve();
        } catch (HttpException $exception) {
            $this->dynamoDbClientLogger->error(
                'Failed to retrieve changes for identifier.',
                [
                    'identifier' => (string)$event,
                    'exception' => $exception
                ]
            );

            throw new RuntimeException('Failed to retrieve changes for identifier.', 0, $exception);
        }

        /** @var EventStateChangeCollection[] $stateChanges */
        $stateChanges = [];
        foreach ($response->getItems() as $item) {
            $stateChange = $this->parseEventStateChange($item);

            if (!array_key_exists($stateChange->getIdentifier(), $stateChanges)) {
                $stateChanges[$stateChange->getIdentifier()] = new EventStateChangeCollection([]);
            }

            $stateChanges[$stateChange->getIdentifier()]->setStateChange($stateChange);
        }

        return $stateChanges;
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
            Provider::from((string)$item['provider']?->getS()),
            (string)(isset($item['identifier']) ? $item['identifier']?->getS() : ''),
            (string)(isset($item['owner']) ? $item['owner']?->getS() : ''),
            (string)(isset($item['repository']) ? $item['repository']?->getS() : ''),
            (int)(isset($item['version']) ? $item['version']?->getN() : 1),
            (array)$this->serializer->decode(
                (string)(isset($item['event']) ? $item['event']?->getS() : '[]'),
                'json'
            ),
            (int)(isset($item['expiry']) ? $item['expiry']?->getN() : '')
        );
    }
}
