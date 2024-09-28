<?php

namespace App\Tests\Client;

use App\Client\DynamoDbClient;
use App\Enum\EnvironmentVariable;
use App\Enum\OrchestratedEventState;
use App\Model\Job;
use Packages\Contracts\Environment\Service;
use AsyncAws\Core\Test\ResultMockFactory;
use AsyncAws\DynamoDb\Enum\ComparisonOperator;
use AsyncAws\DynamoDb\Enum\ReturnValuesOnConditionCheckFailure;
use AsyncAws\DynamoDb\Input\PutItemInput;
use AsyncAws\DynamoDb\Input\QueryInput;
use AsyncAws\DynamoDb\Result\PutItemOutput;
use AsyncAws\DynamoDb\Result\QueryOutput;
use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use AsyncAws\DynamoDb\ValueObject\Condition;
use DateTimeImmutable;
use Packages\Configuration\Mock\MockEnvironmentServiceFactory;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Provider\Provider;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

final class DynamoDbClientTest extends KernelTestCase
{
    public function testArgumentsWhenStoringStateChangeForEvent(): void
    {
        $mockEvent = new Job(
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-commit',
            OrchestratedEventState::SUCCESS,
            new DateTimeImmutable(),
            'mock-external-id'
        );

        $mockClient = $this->createMock(\AsyncAws\DynamoDb\DynamoDbClient::class);

        $client = new DynamoDbClient(
            $mockClient,
            MockEnvironmentServiceFactory::createMock(
                Environment::PRODUCTION,
                Service::ORCHESTRATOR,
                [
                    EnvironmentVariable::EVENT_STORE->value => 'event-store'
                ]
            ),
            $this->getContainer()->get(SerializerInterface::class),
            new NullLogger()
        );

        $mockClient->expects($this->once())
            ->method('putItem')
            ->with(
                self::callback(
                    function (PutItemInput $input) use ($mockEvent): bool {
                        $this->assertEquals(
                            'event-store',
                            $input->getTableName()
                        );
                        // This is _very_ important, as its what will ensure that we don't overwrite
                        // existing state changes for the same event (i.e. contention between writes)
                        $this->assertEquals(
                            'attribute_not_exists(version)',
                            $input->getConditionExpression()
                        );
                        $this->assertEquals(
                            ReturnValuesOnConditionCheckFailure::ALL_OLD,
                            $input->getReturnValuesOnConditionCheckFailure()
                        );

                        $item = $input->getItem();
                        $this->assertEquals(
                            (string)$mockEvent,
                            $item['identifier']->getS()
                        );
                        $this->assertEquals(
                            Provider::GITHUB->value,
                            $item['provider']->getS()
                        );
                        $this->assertEquals(
                            'mock-owner',
                            $item['owner']->getS()
                        );
                        $this->assertEquals(
                            'mock-repository',
                            $item['repository']->getS()
                        );
                        $this->assertEquals(
                            2,
                            $item['version']->getN()
                        );
                        $this->assertEquals(
                            '{"mock":"change"}',
                            $item['event']->getS()
                        );
                        $this->assertEquals(
                            $mockEvent->getEventTime()->format('U'),
                            $item['eventTime']->getN()
                        );
                        return true;
                    }
                )
            )
            ->willReturn(ResultMockFactory::create(PutItemOutput::class));

        $this->assertTrue(
            $client->storeStateChange(
                $mockEvent,
                2,
                ['mock' => 'change']
            )
        );
    }

    public function testArgumentsWhenGettingStateChangesForEvent(): void
    {
        $mockEvent = new Job(
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-commit',
            OrchestratedEventState::SUCCESS,
            new DateTimeImmutable(),
            'mock-external-id'
        );

        $mockClient = $this->createMock(\AsyncAws\DynamoDb\DynamoDbClient::class);

        $client = new DynamoDbClient(
            $mockClient,
            MockEnvironmentServiceFactory::createMock(
                Environment::PRODUCTION,
                Service::ORCHESTRATOR,
                [
                    EnvironmentVariable::EVENT_STORE->value => 'event-store'
                ]
            ),
            $this->getContainer()->get(SerializerInterface::class),
            new NullLogger()
        );

        $mockOutput = ResultMockFactory::create(
            QueryOutput::class,
            [
                'input' => new QueryInput(),
                'Items' => [
                    [
                        'provider' => new AttributeValue(
                            [
                                'S' => Provider::GITHUB->value
                            ]
                        ),
                        'version' => new AttributeValue(
                            [
                                'N' => '1'
                            ]
                        ),
                        'event' => new AttributeValue(
                            [
                                'S' => '{"mock": "item"}'
                            ]
                        )
                    ],
                    [
                        'provider' => new AttributeValue(
                            [
                                'S' => Provider::GITHUB->value
                            ]
                        ),
                        'version' => new AttributeValue(
                            [
                                'N' => '2'
                            ]
                        ),
                        'event' => new AttributeValue(
                            [
                                'S' => '{"mock": "item-2"}'
                            ]
                        )
                    ]
                ]
            ]
        );

        $mockClient->expects($this->once())
            ->method('query')
            ->with(
                self::callback(
                    function (QueryInput $input) use ($mockEvent): bool {
                        $this->assertEquals(
                            'event-store',
                            $input->getTableName()
                        );
                        $this->assertTrue($input->getConsistentRead());
                        $this->assertEquals(
                            [
                                'identifier' => Condition::create([
                                    'AttributeValueList' => [
                                        [
                                            'S' => (string)$mockEvent
                                        ]
                                    ],
                                    'ComparisonOperator' => ComparisonOperator::EQ
                                ])
                            ],
                            $input->getKeyConditions()
                        );
                        return true;
                    }
                )
            )
            ->willReturn($mockOutput);

        $this->assertEquals(
            [
                [
                    'provider' => new AttributeValue(['S' => Provider::GITHUB->value]),
                    'version' => new AttributeValue(['N' => 1]),
                    'event' => new AttributeValue(['S' => '{"mock": "item"}'])
                ],
                [
                    'provider' => new AttributeValue(['S' => Provider::GITHUB->value]),
                    'version' => new AttributeValue(['N' => 2]),
                    'event' => new AttributeValue(['S' => '{"mock": "item-2"}'])
                ]
            ],
            iterator_to_array($client->getStateChangesForEvent($mockEvent))
        );
    }

    public function testGetEventStateChangesForCommit(): void
    {
        $mockEvent = new Job(
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-commit',
            OrchestratedEventState::SUCCESS,
            new DateTimeImmutable(),
            'mock-external-id'
        );

        $mockClient = $this->createMock(\AsyncAws\DynamoDb\DynamoDbClient::class);

        $client = new DynamoDbClient(
            $mockClient,
            MockEnvironmentServiceFactory::createMock(
                Environment::PRODUCTION,
                Service::ORCHESTRATOR,
                [
                    EnvironmentVariable::EVENT_STORE->value => 'event-store'
                ]
            ),
            $this->getContainer()->get(SerializerInterface::class),
            new NullLogger()
        );

        $mockOutput = ResultMockFactory::create(
            QueryOutput::class,
            [
                'input' => new QueryInput(),
                'Items' => [
                    [
                        'provider' => new AttributeValue(
                            [
                                'S' => Provider::GITHUB->value
                            ]
                        ),
                        'identifier' => new AttributeValue(
                            [
                                'S' => 'mock-identifier'
                            ]
                        ),
                        'version' => new AttributeValue(
                            [
                                'N' => '1'
                            ]
                        ),
                        'event' => new AttributeValue(
                            [
                                'S' => '{"mock": "item"}'
                            ]
                        )
                    ],
                    [
                        'provider' => new AttributeValue(
                            [
                                'S' => Provider::GITHUB->value
                            ]
                        ),
                        'identifier' => new AttributeValue(
                            [
                                'S' => 'mock-identifier-2'
                            ]
                        ),
                        'version' => new AttributeValue(
                            [
                                'N' => '1'
                            ]
                        ),
                        'event' => new AttributeValue(
                            [
                                'S' => '{"mock": "item-2"}'
                            ]
                        )
                    ]
                ]
            ]
        );

        $mockClient->expects($this->once())
            ->method('query')
            ->with(
                self::callback(
                    function (QueryInput $input) use ($mockEvent): bool {
                        $this->assertEquals(
                            'event-store',
                            $input->getTableName()
                        );
                        $this->assertEquals(
                            'repositoryIdentifier-commit-index',
                            $input->getIndexName()
                        );
                        $this->assertEquals(
                            [
                                'repositoryIdentifier' => Condition::create([
                                    'AttributeValueList' => [
                                        [
                                            'S' => $mockEvent->getUniqueRepositoryIdentifier()
                                        ]
                                    ],
                                    'ComparisonOperator' => ComparisonOperator::EQ
                                ]),
                                'commit' => Condition::create([
                                    'AttributeValueList' => [
                                        [
                                            'S' => $mockEvent->getCommit()
                                        ]
                                    ],
                                    'ComparisonOperator' => ComparisonOperator::EQ
                                ])
                            ],
                            $input->getKeyConditions()
                        );
                        return true;
                    }
                )
            )
            ->willReturn($mockOutput);

        $this->assertEquals(
            [
                [
                    'provider' => new AttributeValue(['S' => Provider::GITHUB->value]),
                    'version' => new AttributeValue(['N' => 1]),
                    'event' => new AttributeValue(['S' => '{"mock": "item"}']),
                    'identifier' => new AttributeValue(['S' => 'mock-identifier'])
                ],
                [
                    'provider' => new AttributeValue(['S' => Provider::GITHUB->value]),
                    'version' => new AttributeValue(['N' => 1]),
                    'event' => new AttributeValue(['S' => '{"mock": "item-2"}']),
                    'identifier' => new AttributeValue(['S' => 'mock-identifier-2'])
                ]
            ],
            iterator_to_array(
                $client->getEventStateChangesForCommit(
                    $mockEvent->getUniqueRepositoryIdentifier(),
                    $mockEvent->getCommit()
                )
            )
        );
    }
}
