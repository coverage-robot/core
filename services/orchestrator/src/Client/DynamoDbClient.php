<?php

namespace App\Client;

use App\Enum\EnvironmentVariable;
use App\Model\OrchestratedEventInterface;
use App\Service\EnvironmentService;
use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\DynamoDb\Enum\ReturnValuesOnConditionCheckFailure;
use AsyncAws\DynamoDb\Input\QueryInput;
use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class DynamoDbClient
{
    /**
     * The default TTL for each event in the store, in seconds - currently 12 hours.
     */
    private const DEFAULT_EVENT_TTL = 43200;

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
                            'S' => $event->getIdentifier(),
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
                            'N' => $version
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
                        'ConsistentRead' => true,
                        'Key' => [
                            'identifier' => [
                                'S' => $identifier,
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
        }

        $changes = array_column(
            $response->getItems(),
            'event'
        );

        return array_map(
            static fn (AttributeValue $item) => $item->getS(),
            $changes
        );
    }
}
