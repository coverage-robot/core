<?php

namespace Packages\Configuration\Tests\Client;

use AsyncAws\Core\Test\ResultMockFactory;
use AsyncAws\DynamoDb\Enum\ComparisonOperator;
use AsyncAws\DynamoDb\Enum\Select;
use AsyncAws\DynamoDb\Input\DeleteItemInput;
use AsyncAws\DynamoDb\Input\PutItemInput;
use AsyncAws\DynamoDb\Input\QueryInput;
use AsyncAws\DynamoDb\Result\DeleteItemOutput;
use AsyncAws\DynamoDb\Result\PutItemOutput;
use AsyncAws\DynamoDb\Result\QueryOutput;
use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use AsyncAws\DynamoDb\ValueObject\Condition;
use Packages\Configuration\Client\DynamoDbClient;
use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Enum\SettingValueType;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class DynamoDbClientTest extends TestCase
{
    public function testArgumentsWhenStoringSettingValue(): void
    {
        $mockClient = $this->createMock(\AsyncAws\DynamoDb\DynamoDbClient::class);

        $mockEnvironmentService = $this->createMock(EnvironmentServiceInterface::class);
        $mockEnvironmentService->expects($this->once())
            ->method('getEnvironment')
            ->willReturn(Environment::TESTING);

        $client = new DynamoDbClient(
            $mockEnvironmentService,
            new NullLogger(),
            $mockClient
        );

        $mockClient->expects($this->once())
            ->method('putItem')
            ->with(
                self::callback(
                    function (PutItemInput $input): bool {
                        $this->assertEquals(
                            'coverage-configuration-test',
                            $input->getTableName()
                        );

                        $item = $input->getItem();
                        $this->assertEquals(
                            'github-mock-owner-mock-repository',
                            $item[DynamoDbClient::REPOSITORY_IDENTIFIER_COLUMN]->getS()
                        );
                        $this->assertEquals(
                            SettingKey::LINE_ANNOTATION->value,
                            $item[DynamoDbClient::SETTING_KEY_COLUMN]->getS()
                        );
                        $this->assertEquals(
                            true,
                            $item[DynamoDbClient::VALUE_COLUMN]->getBool()
                        );
                        return true;
                    }
                )
            )
            ->willReturn(ResultMockFactory::create(PutItemOutput::class));

        $this->assertTrue(
            $client->setSettingInStore(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                SettingKey::LINE_ANNOTATION,
                SettingValueType::BOOLEAN,
                true
            )

        );
    }

    public function testArgumentsWhenGettingSettingValue(): void
    {
        $mockClient = $this->createMock(\AsyncAws\DynamoDb\DynamoDbClient::class);

        $mockEnvironmentService = $this->createMock(EnvironmentServiceInterface::class);
        $mockEnvironmentService->expects($this->once())
            ->method('getEnvironment')
            ->willReturn(Environment::TESTING);

        $client = new DynamoDbClient(
            $mockEnvironmentService,
            new NullLogger(),
            $mockClient
        );

        $mockClient->expects($this->once())
            ->method('query')
            ->with(
                self::callback(
                    function (QueryInput $input): bool {
                        $this->assertEquals(
                            'coverage-configuration-test',
                            $input->getTableName()
                        );
                        $this->assertEquals(
                            Select::SPECIFIC_ATTRIBUTES,
                            $input->getSelect()
                        );
                        $this->assertEquals(
                            [DynamoDbClient::VALUE_COLUMN],
                            $input->getAttributesToGet()
                        );
                        $this->assertEquals(
                            [
                                DynamoDbClient::REPOSITORY_IDENTIFIER_COLUMN => new Condition(
                                    [
                                        'AttributeValueList' => [
                                            [
                                                'S' => 'github-mock-owner-mock-repository'
                                            ]
                                        ],
                                        'ComparisonOperator' => ComparisonOperator::EQ
                                    ]
                                ),
                                DynamoDbClient::SETTING_KEY_COLUMN => new Condition(
                                    [
                                        'AttributeValueList' => [
                                            [
                                                'S' => SettingKey::LINE_ANNOTATION->value
                                            ]
                                        ],
                                        'ComparisonOperator' => ComparisonOperator::EQ
                                    ]
                                ),
                            ],
                            $input->getKeyConditions()
                        );
                        return true;
                    }
                )
            )
            ->willReturn(
                ResultMockFactory::create(
                    QueryOutput::class,
                    [
                        'input' => new QueryInput(),
                        'Items' => [
                            [
                                DynamoDbClient::VALUE_COLUMN => new AttributeValue(
                                    [
                                        SettingValueType::BOOLEAN->value => true
                                    ]
                                )
                            ],
                        ]
                    ]
                )
            );

        $this->assertEquals(
            true,
            $client->getSettingFromStore(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                SettingKey::LINE_ANNOTATION,
                SettingValueType::BOOLEAN
            )
        );
    }

    public function testArgumentsWhenDeletingSettingValue(): void
    {
        $mockClient = $this->createMock(\AsyncAws\DynamoDb\DynamoDbClient::class);

        $mockEnvironmentService = $this->createMock(EnvironmentServiceInterface::class);
        $mockEnvironmentService->expects($this->once())
            ->method('getEnvironment')
            ->willReturn(Environment::TESTING);

        $client = new DynamoDbClient(
            $mockEnvironmentService,
            new NullLogger(),
            $mockClient
        );

        $mockClient->expects($this->once())
            ->method('deleteItem')
            ->with(
                self::callback(
                    function (DeleteItemInput $input): bool {
                        $this->assertEquals(
                            'coverage-configuration-test',
                            $input->getTableName()
                        );
                        $this->assertEquals(
                            [
                                DynamoDbClient::REPOSITORY_IDENTIFIER_COLUMN => new AttributeValue(
                                    [
                                        'S' => 'github-mock-owner-mock-repository'
                                    ]
                                ),
                                DynamoDbClient::SETTING_KEY_COLUMN => new AttributeValue(
                                    [
                                        'S' => SettingKey::LINE_ANNOTATION->value
                                    ]
                                ),
                            ],
                            $input->getKey()
                        );
                        return true;
                    }
                )
            )
            ->willReturn(ResultMockFactory::create(DeleteItemOutput::class));

        $this->assertEquals(
            true,
            $client->deleteSettingFromStore(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                SettingKey::LINE_ANNOTATION
            )
        );
    }
}
