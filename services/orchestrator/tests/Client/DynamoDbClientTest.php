<?php

namespace App\Tests\Client;

use App\Client\DynamoDbClient;
use App\Enum\EnvironmentVariable;
use App\Enum\OrchestratedEventState;
use App\Model\EventStateChange;
use App\Model\Job;
use App\Tests\Mock\Factory\MockEnvironmentServiceFactory;
use AsyncAws\Core\Test\ResultMockFactory;
use AsyncAws\DynamoDb\Enum\ComparisonOperator;
use AsyncAws\DynamoDb\Enum\ReturnValuesOnConditionCheckFailure;
use AsyncAws\DynamoDb\Input\PutItemInput;
use AsyncAws\DynamoDb\Input\QueryInput;
use AsyncAws\DynamoDb\Result\PutItemOutput;
use AsyncAws\DynamoDb\Result\QueryOutput;
use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use AsyncAws\DynamoDb\ValueObject\Condition;
use Packages\Models\Enum\Environment;
use Packages\Models\Enum\Provider;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

class DynamoDbClientTest extends KernelTestCase
{
    public function testArgumentsWhenStoringStateChangeForEvent(): void
    {
        $mockEvent = new Job(
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-commit',
            OrchestratedEventState::SUCCESS,
            'mock-external-id'
        );

        $mockClient = $this->createMock(\AsyncAws\DynamoDb\DynamoDbClient::class);

        $client = new DynamoDbClient(
            $mockClient,
            MockEnvironmentServiceFactory::getMock(
                $this,
                Environment::PRODUCTION,
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
                    function (PutItemInput $input) use ($mockEvent) {
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
            'mock-external-id'
        );

        $mockClient = $this->createMock(\AsyncAws\DynamoDb\DynamoDbClient::class);

        $client = new DynamoDbClient(
            $mockClient,
            MockEnvironmentServiceFactory::getMock(
                $this,
                Environment::PRODUCTION,
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
                    function (QueryInput $input) use ($mockEvent) {
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
                1 => new EventStateChange(
                    Provider::GITHUB,
                    '',
                    '',
                    '',
                    1,
                    [
                        'mock' => 'item'
                    ],
                    0
                ),
                2 => new EventStateChange(
                    Provider::GITHUB,
                    '',
                    '',
                    '',
                    2,
                    [
                        'mock' => 'item-2'
                    ],
                    0
                )
            ],
            $client->getStateChangesForEvent($mockEvent)
                ->getEvents()
        );
    }
}
