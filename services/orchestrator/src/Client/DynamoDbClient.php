<?php

namespace App\Client;

use App\Enum\EnvironmentVariable;
use App\Model\OrchestratedEventInterface;
use App\Service\EnvironmentService;
use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\DynamoDb\Enum\ComparisonOperator;
use AsyncAws\DynamoDb\Enum\ReturnValuesOnConditionCheckFailure;
use AsyncAws\DynamoDb\Enum\Select;
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

    public function storeEventChange(OrchestratedEventInterface $event, int $version, array $change): bool
    {
        try {
            $response = $this->dynamoDbClient->putItem(
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
     * @return array<array-key, string>
     *
     * @throws RuntimeException
     */
    public function getStateChangesByIdentifier(string $identifier): array
    {
        try {
            $response = $this->dynamoDbClient->query(
                new QueryInput(
                    [
                        'TableName' => $this->environmentService->getVariable(
                            EnvironmentVariable::EVENT_STORE
                        ),
                        'Select' => Select::ALL_ATTRIBUTES,
                        'ConsistentRead' => true,
                        'KeyConditions' => [
                            'identifier' => [
                                'AttributeValueList' => [
                                    [
                                        'S' => $identifier
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
                    'identifier' => $identifier,
                    'exception' => $exception
                ]
            );

            throw new RuntimeException('Failed to retrieve changes for identifier.', 0, $exception);
        }

        $stateChanges = [];
        foreach ($response->getItems() as $item) {
            $stateChanges[] = $this->serializer->decode(
                $item['event']->getS(),
                'json'
            );
        }

        return $stateChanges;
    }
}
