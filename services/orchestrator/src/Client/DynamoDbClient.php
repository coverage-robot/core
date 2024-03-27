<?php

namespace App\Client;

use App\Enum\EnvironmentVariable;
use App\Model\OrchestratedEventInterface;
use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\DynamoDb\Enum\ComparisonOperator;
use AsyncAws\DynamoDb\Enum\ReturnValuesOnConditionCheckFailure;
use AsyncAws\DynamoDb\Enum\Select;
use AsyncAws\DynamoDb\Exception\ConditionalCheckFailedException;
use AsyncAws\DynamoDb\Input\PutItemInput;
use AsyncAws\DynamoDb\Input\QueryInput;
use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use Override;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class DynamoDbClient implements DynamoDbClientInterface
{
    /**
     * The default TTL for each event in the store, in seconds - currently 12 hours.
     */
    private const int DEFAULT_EVENT_TTL = 43200;

    private const string REPOSITORY_IDENTIFIER_COLUMN = 'repositoryIdentifier';

    private const string COMMIT_COLUMN = 'commit';

    private const string VERSION_COLUMN = 'version';

    /**
     * The name of the index used to query for all of the events for a particular
     * repository and commit.
     */
    private const REPOSITORY_COMMIT_INDEX = self:: REPOSITORY_IDENTIFIER_COLUMN . '-' . self::COMMIT_COLUMN . '-index';

    public function __construct(
        private readonly \AsyncAws\DynamoDb\DynamoDbClient $dynamoDbClient,
        private readonly EnvironmentServiceInterface $environmentService,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * @inheritDoc
     *
     * @throws ConditionalCheckFailedException
     * @throws HttpException
     */
    #[Override]
    public function storeStateChange(OrchestratedEventInterface $event, int $version, array $change): bool
    {
        $response = $this->dynamoDbClient->putItem(
            new PutItemInput(
                [
                    'TableName' => $this->environmentService->getVariable(EnvironmentVariable::EVENT_STORE),
                    'ConditionExpression' => sprintf('attribute_not_exists(%s)', self::VERSION_COLUMN),
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
                        self::VERSION_COLUMN => [
                            'N' => (string)$version
                        ],
                        'event' => [
                            'S' => $this->serializer->serialize($change, 'json')
                        ],
                        'expiry' => [
                            'N' => (string)(time() + self::DEFAULT_EVENT_TTL)
                        ],
                        'eventTime' => [
                            'N' => $event->getEventTime()->format('U')
                        ],
                    ],
                ]
            )
        );

        $response->resolve();

        return true;
    }

    /**
     * @inheritDoc
     *
     * @throws HttpException
     */
    #[Override]
    public function getStateChangesForEvent(OrchestratedEventInterface $event): iterable
    {
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

        return $response->getItems();
    }

    /**
     * @inheritDoc
     *
     * @throws HttpException
     */
    #[Override]
    public function getEventStateChangesForCommit(string $repositoryIdentifier, string $commit): iterable
    {
        $response = $this->dynamoDbClient->query(
            new QueryInput(
                [
                    'TableName' => $this->environmentService->getVariable(
                        EnvironmentVariable::EVENT_STORE
                    ),
                    'Select' => Select::ALL_ATTRIBUTES,
                    /**
                     * Use the index to query for all of the events for a particular repository and
                     * commit. This helps us optimise query performance across the event store.
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
                                    'S' => $repositoryIdentifier
                                ]
                            ],
                            'ComparisonOperator' => ComparisonOperator::EQ
                        ],
                        self::COMMIT_COLUMN => [
                            'AttributeValueList' => [
                                [
                                    'S' => $commit
                                ]
                            ],
                            'ComparisonOperator' => ComparisonOperator::EQ
                        ],
                    ],
                ]
            )
        );

        $response->resolve();

        return $response->getItems();
    }
}
