<?php

namespace App\Client;

use App\Enum\EnvironmentVariable;
use App\Model\OrchestratedEventInterface;
use App\Service\EnvironmentService;
use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\DynamoDb\Enum\ComparisonOperator;
use AsyncAws\DynamoDb\Enum\ReturnValuesOnConditionCheckFailure;
use AsyncAws\DynamoDb\Enum\Select;
use AsyncAws\DynamoDb\Input\PutItemInput;
use AsyncAws\DynamoDb\Input\QueryInput;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\SerializerInterface;

class DynamoDbClient
{
    /**
     * The default TTL for each event in the store, in seconds - currently 12 hours.
     */
    private const DEFAULT_EVENT_TTL = 43200;

    /**
     * @param SerializerInterface&DecoderInterface $serializer
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
                                'S' => (string)$event,
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
    public function getStateChangesForEvent(OrchestratedEventInterface $event): array
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
                                        'S' => (string)$event
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

        /** @var list<array> $stateChanges */
        $stateChanges = [];
        foreach ($response->getItems() as $item) {
            /** @var array $stateChange */
            $stateChange = $this->serializer->decode(
                $item['event']->getS() ?? '[]',
                'json'
            );

            $stateChanges[] = $stateChange;
        }

        return $stateChanges;
    }
}
