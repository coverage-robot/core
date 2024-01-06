<?php

namespace Packages\Configuration\Client;

use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\DynamoDb\Enum\ComparisonOperator;
use AsyncAws\DynamoDb\Enum\Select;
use AsyncAws\DynamoDb\Input\DeleteItemInput;
use AsyncAws\DynamoDb\Input\PutItemInput;
use AsyncAws\DynamoDb\Input\QueryInput;
use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Enum\SettingValueType;
use Packages\Configuration\Exception\SettingNotFoundException;
use Packages\Configuration\Exception\SettingRetrievalFailedException;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Contracts\Provider\Provider;
use Psr\Log\LoggerInterface;

class DynamoDbClient
{
    /**
     * The table name which stores the configuration settings.
     *
     * This is dynamic based on the environment the application is running in
     * (i.e. coverage-configuration-prod, coverage-configuration-dev, etc).
     */
    private const string TABLE_NAME = 'coverage-configuration-%s';

    /**
     * The primary key for the configuration store.
     *
     * This is the grouping for the settings, which is the provider, owner and repository.
     */
    public const string REPOSITORY_IDENTIFIER_COLUMN = 'repositoryIdentifier';

    /**
     * The range key for the configuration store.
     *
     * This is the unique setting identifier (in dot notation).
     */
    public const string SETTING_KEY_COLUMN = 'settingKey';

    /**
     * The value column for the configuration store.
     *
     * This is the value of the setting for a repository.
     */
    public const string VALUE_COLUMN = 'value';

    public function __construct(
        private readonly EnvironmentServiceInterface $environmentService,
        private readonly LoggerInterface $dynamoDbClientLogger,
        private readonly \AsyncAws\DynamoDb\DynamoDbClient $dynamoDbClient
    ) {
    }

    public function getSettingFromStore(
        Provider $provider,
        string $owner,
        string $repository,
        SettingKey $key,
        SettingValueType $type
    ): mixed {
        try {
            $response = $this->dynamoDbClient->query(
                new QueryInput(
                    [
                        'TableName' => sprintf(
                            self::TABLE_NAME,
                            $this->environmentService->getEnvironment()
                                ->value
                        ),
                        'Select' => Select::SPECIFIC_ATTRIBUTES,
                        'AttributesToGet' => [self::VALUE_COLUMN],
                        'KeyConditions' => [
                            self::REPOSITORY_IDENTIFIER_COLUMN => [
                                'AttributeValueList' => [
                                    [
                                        'S' => sprintf(
                                            '%s-%s-%s',
                                            $provider->value,
                                            $owner,
                                            $repository
                                        )
                                    ]
                                ],
                                'ComparisonOperator' => ComparisonOperator::EQ
                            ],
                            self::SETTING_KEY_COLUMN => [
                                'AttributeValueList' => [
                                    [
                                        'S' => $key->value
                                    ]
                                ],
                                'ComparisonOperator' => ComparisonOperator::EQ
                            ],
                        ],
                    ]
                )
            );

            $response->resolve();

            $settings = iterator_to_array($response->getItems());

            if (count($settings) === 0) {
                throw new SettingNotFoundException(
                    sprintf(
                        'Setting %s not found',
                        $key->value
                    )
                );
            }

            $setting = reset($settings);

            $value = $setting['value']->{"get" . ucfirst(strtolower($type->value))}();
        } catch (HttpException $httpException) {
            $this->dynamoDbClientLogger->error(
                sprintf(
                    'Failed to get setting %s',
                    $key->value
                ),
                [
                    'provider' => $provider,
                    'owner' => $owner,
                    'repository' => $repository,
                    'exception' => $httpException,
                ]
            );

            throw new SettingRetrievalFailedException(
                sprintf(
                    'Failed to retrieve setting value for %s',
                    $key->value
                ),
                0,
                $httpException
            );
        }
        return $value;
    }

    public function setSettingInStore(
        Provider $provider,
        string $owner,
        string $repository,
        SettingKey $key,
        SettingValueType $type,
        mixed $value
    ): bool {
        try {
            $response = $this->dynamoDbClient->putItem(
                new PutItemInput(
                    [
                        'TableName' => sprintf(
                            self::TABLE_NAME,
                            $this->environmentService->getEnvironment()
                                ->value
                        ),
                        'Item' => [
                            self::REPOSITORY_IDENTIFIER_COLUMN => [
                                'S' => sprintf(
                                    '%s-%s-%s',
                                    $provider->value,
                                    $owner,
                                    $repository
                                ),
                            ],
                            self::SETTING_KEY_COLUMN => [
                                'S' => $key->value,
                            ],
                            self::VALUE_COLUMN => [
                                $type->value => $value,
                            ]
                        ],
                    ]
                )
            );

            $response->resolve();
        } catch (HttpException $httpException) {
            $this->dynamoDbClientLogger->error(
                sprintf(
                    'Failed to set setting %s',
                    $key->value
                ),
                [
                    'provider' => $provider,
                    'owner' => $owner,
                    'repository' => $repository,
                    'exception' => $httpException,
                ]
            );

            return false;
        }

        return true;
    }

    public function deleteSettingFromStore(
        Provider $provider,
        string $owner,
        string $repository,
        SettingKey $key
    ): bool {
        try {
            $response = $this->dynamoDbClient->deleteItem(
                new DeleteItemInput(
                    [
                        'TableName' => sprintf(
                            self::TABLE_NAME,
                            $this->environmentService->getEnvironment()
                                ->value
                        ),
                        'Key' => [
                            self::REPOSITORY_IDENTIFIER_COLUMN => [
                                'S' => sprintf(
                                    '%s-%s-%s',
                                    $provider->value,
                                    $owner,
                                    $repository
                                ),
                            ],
                            self::SETTING_KEY_COLUMN => [
                                'S' => $key->value,
                            ],
                        ],
                    ]
                )
            );

            $response->resolve();
        } catch (HttpException $httpException) {
            $this->dynamoDbClientLogger->error(
                sprintf(
                    'Failed to delete setting %s',
                    $key->value
                ),
                [
                    'provider' => $provider,
                    'owner' => $owner,
                    'repository' => $repository,
                    'exception' => $httpException,
                ]
            );

            return false;
        }

        return true;
    }
}
