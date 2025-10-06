<?php

declare(strict_types=1);

namespace App\Client;

use App\Enum\EnvironmentVariable;
use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\DynamoDb\Enum\ComparisonOperator;
use AsyncAws\DynamoDb\Enum\Select;
use AsyncAws\DynamoDb\Exception\ConditionalCheckFailedException;
use AsyncAws\DynamoDb\Input\PutItemInput;
use AsyncAws\DynamoDb\Input\QueryInput;
use Override;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Contracts\Provider\Provider;
use Psr\Log\LoggerInterface;

final readonly class DynamoDbClient implements DynamoDbClientInterface
{
    private const string REPOSITORY_IDENTIFIER_COLUMN = 'repositoryIdentifier';

    private const string REF_COLUMN = 'ref';

    private const string COVERAGE_PERCENTAGE_COLUMN = 'coveragePercentage';

    public function __construct(
        private \AsyncAws\DynamoDb\DynamoDbClient $dynamoDbClient,
        private EnvironmentServiceInterface $environmentService,
        private LoggerInterface $dynamoDbClientLogger
    ) {
    }

    /**
     * @inheritDoc
     *
     * @throws ConditionalCheckFailedException
     * @throws HttpException
     */
    #[Override]
    public function setCoveragePercentage(
        Provider $provider,
        string $owner,
        string $repository,
        string $ref,
        float $coveragePercentage
    ): void {
        try {
            $response = $this->dynamoDbClient->putItem(
                new PutItemInput(
                    [
                        'TableName' => $this->environmentService->getVariable(EnvironmentVariable::REF_METADATA_TABLE),
                        'Item' => [
                            self::REPOSITORY_IDENTIFIER_COLUMN => [
                                'S' => $this->getUniqueRepositoryIdentifier($provider, $owner, $repository),
                            ],
                            self::REF_COLUMN => [
                                'S' => $ref
                            ],
                            self::COVERAGE_PERCENTAGE_COLUMN => [
                                'N' => (string)$coveragePercentage
                            ]
                        ],
                    ]
                )
            );

            $response->resolve();
        } catch (HttpException $httpException) {
            $this->dynamoDbClientLogger->error(
                'Failed to set coverage percentage for ref.',
                [
                    'provider' => $provider,
                    'owner' => $owner,
                    'repository' => $repository,
                    'ref' => $ref,
                    'coveragePercentage' => $coveragePercentage,
                    'exception' => $httpException,
                ]
            );

            throw $httpException;
        }
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getCoveragePercentage(
        Provider $provider,
        string $owner,
        string $repository,
        string $ref
    ): ?float {
        try {
            $response = $this->dynamoDbClient->query(
                new QueryInput(
                    [
                        'TableName' => $this->environmentService->getVariable(
                            EnvironmentVariable::REF_METADATA_TABLE
                        ),
                        'Select' => Select::SPECIFIC_ATTRIBUTES,
                        'Limit' => 1,
                        'ProjectionExpression' => self::COVERAGE_PERCENTAGE_COLUMN,
                        'KeyConditions' => [
                            self::REPOSITORY_IDENTIFIER_COLUMN => [
                                'AttributeValueList' => [
                                    [
                                        'S' => $this->getUniqueRepositoryIdentifier($provider, $owner, $repository)
                                    ]
                                ],
                                'ComparisonOperator' => ComparisonOperator::EQ
                            ],
                            self::REF_COLUMN => [
                                'AttributeValueList' => [
                                    [
                                        'S' => $ref
                                    ]
                                ],
                                'ComparisonOperator' => ComparisonOperator::EQ
                            ],
                        ],
                    ]
                )
            );

            $response->resolve();
        } catch (HttpException $httpException) {
            $this->dynamoDbClientLogger->error(
                'Failed to get coverage percentage for ref.',
                [
                    'provider' => $provider,
                    'owner' => $owner,
                    'repository' => $repository,
                    'ref' => $ref,
                    'exception' => $httpException,
                ]
            );

            return null;
        }

        foreach ($response->getItems(true) as $item) {
            /**
             * Grab the first item out of the response (iterable), which should be the ref we're
             * looking for
             */
            return $item[self::COVERAGE_PERCENTAGE_COLUMN]->getN() !== null ?
                (float)$item[self::COVERAGE_PERCENTAGE_COLUMN]->getN()
                : null;
        }

        return null;
    }

    private function getUniqueRepositoryIdentifier(
        Provider $provider,
        string $owner,
        string $repository
    ): string {
        return sprintf(
            '%s-%s-%s',
            $owner,
            $provider->value,
            $repository
        );
    }
}
