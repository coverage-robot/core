<?php

namespace App\Tests\Service;

use App\Client\DynamoDbClient;
use App\Enum\OrchestratedEvent;
use App\Enum\OrchestratedEventState;
use App\Model\EventStateChange;
use App\Model\EventStateChangeCollection;
use App\Model\Ingestion;
use App\Model\Job;
use App\Model\OrchestratedEventInterface;
use App\Service\EventStoreService;
use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

class EventStoreServiceTest extends KernelTestCase
{
    #[DataProvider('orchestratedEventsDataProvider')]
    public function testCalculatingStateChangeBetweenEvents(
        ?OrchestratedEventInterface $currentState,
        OrchestratedEventInterface $newState,
        array $expectedStateChange
    ): void {
        $eventStoreService = new EventStoreService(
            $this->getContainer()->get(SerializerInterface::class),
            $this->createMock(DynamoDbClient::class),
            new NullLogger()
        );

        $stateChange = $eventStoreService->getStateChangesBetweenEvent(
            $currentState,
            $newState
        );

        $this->assertEquals(
            $expectedStateChange,
            $stateChange
        );
    }

    public function testThrowsExceptionWhenEventsAreNotComparable(): void
    {
        $eventStoreService = new EventStoreService(
            $this->getContainer()->get(SerializerInterface::class),
            $this->createMock(DynamoDbClient::class),
            new NullLogger()
        );

        $this->expectException(InvalidArgumentException::class);

        $eventStoreService->getStateChangesBetweenEvent(
            new Ingestion(
                Provider::GITHUB,
                'mock-owner',
                'mock-repo',
                '1',
                'mock-upload-id',
                OrchestratedEventState::ONGOING,
                new DateTimeImmutable()
            ),
            new Job(
                Provider::GITHUB,
                'mock-owner',
                'mock-repo',
                '1',
                OrchestratedEventState::ONGOING,
                new DateTimeImmutable(),
                'mock-external-id'
            )
        );
    }

    #[DataProvider('stateChangesDataProvider')]
    public function testReduceStateChanges(
        EventStateChangeCollection $stateChanges,
        OrchestratedEventInterface $expectedReducedState
    ): void {
        $eventStoreService = new EventStoreService(
            $this->getContainer()->get(SerializerInterface::class),
            $this->createMock(DynamoDbClient::class),
            new NullLogger()
        );

        $reducedState = $eventStoreService->reduceStateChangesToEvent(
            $stateChanges
        );

        $this->assertEquals(
            $expectedReducedState,
            $reducedState
        );
    }

    public function testReducingStateChangesWithCorruptEventSource(): void
    {
        $eventStoreService = new EventStoreService(
            $this->getContainer()->get(SerializerInterface::class),
            $this->createMock(DynamoDbClient::class),
            new NullLogger()
        );

        $previousEvent = $eventStoreService->reduceStateChangesToEvent(
            new EventStateChangeCollection(
                [
                    new EventStateChange(
                        Provider::GITHUB,
                        'mock-identifier',
                        'mock-owner',
                        'mock-repository',
                        1,
                        [
                            'some' => 'invalid',
                            'values' => ['x','y'],
                            'type' => OrchestratedEvent::JOB->value,
                        ],
                        null
                    ),
                    new EventStateChange(
                        Provider::GITHUB,
                        'mock-identifier',
                        'mock-owner',
                        'mock-repository',
                        2,
                        [
                            'new-value' => 'z'
                        ],
                        null
                    )
                ]
            )
        );

        $this->assertNull($previousEvent);
    }

    public function testStoringEmptyStateChange(): void
    {
        /**
         * @var SerializerInterface $serializer
         */
        $serializer = $this->getContainer()->get(SerializerInterface::class);

        $eventTime = new DateTimeImmutable();

        $event = new Ingestion(
            Provider::GITHUB,
            'mock-owner',
            'mock-repo',
            '1',
            'mock-upload-id',
            OrchestratedEventState::SUCCESS,
            $eventTime
        );

        $mockDynamoDbClient = $this->createMock(DynamoDbClient::class);
        $mockDynamoDbClient->expects($this->once())
            ->method('getStateChangesForEvent')
            ->willReturn([
                [
                    'provider' => new AttributeValue(['S' => Provider::GITHUB->value]),
                    'version' => new AttributeValue(['N' => 1]),
                    'event' => new AttributeValue(['S' => $serializer->serialize($event, 'json')]),
                ],
            ]);
        $mockDynamoDbClient->expects($this->never())
            ->method('storeStateChange');

        $eventStoreService = new EventStoreService(
            $serializer,
            $mockDynamoDbClient,
            new NullLogger()
        );

        $stateChange = $eventStoreService->storeStateChange($event);

        $this->assertEquals(
            [],
            $stateChange->getEvent()
        );
    }

    public function testStoringStateChanges(): void
    {
        /**
         * @var SerializerInterface $serializer
         */
        $serializer = $this->getContainer()->get(SerializerInterface::class);

        $eventTime = new DateTimeImmutable();

        $ongoingEvent = new Ingestion(
            Provider::GITHUB,
            'mock-owner',
            'mock-repo',
            '1',
            'mock-upload-id',
            OrchestratedEventState::ONGOING,
            $eventTime
        );

        $completeEvent = new Ingestion(
            Provider::GITHUB,
            'mock-owner',
            'mock-repo',
            '1',
            'mock-upload-id',
            OrchestratedEventState::SUCCESS,
            $eventTime
        );

        $mockDynamoDbClient = $this->createMock(DynamoDbClient::class);
        $mockDynamoDbClient->expects($this->once())
            ->method('getStateChangesForEvent')
            ->willReturn([
                [
                    'provider' => new AttributeValue(['S' => Provider::GITHUB->value]),
                    'version' => new AttributeValue(['N' => 1]),
                    'event' => new AttributeValue(['S' => $serializer->serialize($ongoingEvent, 'json')]),
                ],
            ]);
        $mockDynamoDbClient->expects($this->once())
            ->method('storeStateChange')
            ->with(
                $completeEvent,
                2,
                [
                    'state' => OrchestratedEventState::SUCCESS->value,
                ]
            )
            ->willReturn(true);

        $eventStoreService = new EventStoreService(
            $serializer,
            $mockDynamoDbClient,
            new NullLogger()
        );

        $stateChange = $eventStoreService->storeStateChange($completeEvent);

        $this->assertEquals(
            2,
            $stateChange->getVersion()
        );
        $this->assertEquals(
            [
                'state' => OrchestratedEventState::SUCCESS->value,
            ],
            $stateChange->getEvent()
        );
    }


    public function testGettingStateChangesForCommit(): void
    {
        /**
         * @var SerializerInterface $serializer
         */
        $serializer = $this->getContainer()->get(SerializerInterface::class);

        $mockDynamoDbClient = $this->createMock(DynamoDbClient::class);
        $mockDynamoDbClient->expects($this->once())
            ->method('getEventStateChangesForCommit')
            ->willReturn([
                [
                    'provider' => new AttributeValue(['S' => Provider::GITHUB->value]),
                    'version' => new AttributeValue(['N' => 1]),
                    'identifier' => new AttributeValue(['S' => 'mock-identifier-1']),
                    'event' => new AttributeValue(['S' => '{"mock": "value"}']),
                ],
                [
                    'provider' => new AttributeValue(['S' => Provider::GITHUB->value]),
                    'version' => new AttributeValue(['N' => 1]),
                    'identifier' => new AttributeValue(['S' => 'mock-identifier-2']),
                    'event' => new AttributeValue(['S' => '{"mock": "value-2"}']),
                ],
                [
                    'provider' => new AttributeValue(['S' => Provider::GITHUB->value]),
                    'version' => new AttributeValue(['N' => 2]),
                    'identifier' => new AttributeValue(['S' => 'mock-identifier-2']),
                    'event' => new AttributeValue(['S' => '{"mock": "value-3"}']),
                ],
            ]);

        $eventStoreService = new EventStoreService(
            $serializer,
            $mockDynamoDbClient,
            new NullLogger()
        );

        $stateChanges = $eventStoreService->getAllStateChangesForCommit(
            'mock-repository-identifier',
            'mock-commit'
        );

        $this->assertEquals(
            [
                1 => new EventStateChange(
                    Provider::GITHUB,
                    'mock-identifier-1',
                    '',
                    '',
                    1,
                    [
                        'mock' => 'value'
                    ],
                    null
                )
            ],
            $stateChanges['mock-identifier-1']->getEvents()
        );
        $this->assertEquals(
            [
                1 =>  new EventStateChange(
                    Provider::GITHUB,
                    'mock-identifier-2',
                    '',
                    '',
                    1,
                    [
                        'mock' => 'value-2'
                    ],
                    null
                ),
                2 =>  new EventStateChange(
                    Provider::GITHUB,
                    'mock-identifier-2',
                    '',
                    '',
                    2,
                    [
                        'mock' => 'value-3'
                    ],
                    null
                )
            ],
            $stateChanges['mock-identifier-2']->getEvents()
        );
    }

    public static function orchestratedEventsDataProvider(): array
    {
        $eventTime = new DateTimeImmutable('2021-01-01T00:00:00+00:00');

        return [
            [
                null,
                new Ingestion(
                    Provider::GITHUB,
                    'mock-owner',
                    'mock-repo',
                    '1',
                    'mock-upload-id',
                    OrchestratedEventState::ONGOING,
                    $eventTime
                ),
                [
                    'provider' => Provider::GITHUB->value,
                    'owner' => 'mock-owner',
                    'repository' => 'mock-repo',
                    'commit' => '1',
                    'uploadId' => 'mock-upload-id',
                    'state' => OrchestratedEventState::ONGOING->value,
                    'type' => OrchestratedEvent::INGESTION->value,
                    'eventTime' => $eventTime->format(DateTimeImmutable::ATOM)
                ]
            ],
            [
                new Ingestion(
                    Provider::GITHUB,
                    'mock-owner',
                    'mock-repo',
                    '1',
                    'mock-upload-id',
                    OrchestratedEventState::ONGOING,
                    $eventTime
                ),
                new Ingestion(
                    Provider::GITHUB,
                    'mock-owner',
                    'mock-repo',
                    '1',
                    'mock-upload-id',
                    OrchestratedEventState::SUCCESS,
                    $eventTime
                ),
                [
                    'state' => OrchestratedEventState::SUCCESS->value
                ]
            ],
            [
                new Ingestion(
                    Provider::GITHUB,
                    'mock-owner',
                    'mock-repo',
                    '1',
                    'mock-upload-id',
                    OrchestratedEventState::ONGOING,
                    $eventTime
                ),
                new Ingestion(
                    Provider::GITHUB,
                    'mock-owner',
                    'mock-repo',
                    '1',
                    'mock-upload-id',
                    OrchestratedEventState::SUCCESS,
                    $eventTime->add(new DateInterval('PT1S'))
                ),
                [
                    'state' => OrchestratedEventState::SUCCESS->value,
                    'eventTime' => $eventTime->add(new DateInterval('PT1S'))
                        ->format(DateTimeImmutable::ATOM)
                ]
            ]
        ];
    }

    public static function stateChangesDataProvider(): array
    {
        $eventTime = new DateTimeImmutable('2021-01-01T00:00:00+00:00');

        return [
            [
                new EventStateChangeCollection(
                    [
                        new EventStateChange(
                            Provider::GITHUB,
                            'mock-identifier',
                            'mock-owner',
                            'mock-repository',
                            1,
                            [
                                'provider' => Provider::GITHUB->value,
                                'owner' => 'mock-owner',
                                'repository' => 'mock-repo',
                                'commit' => '1',
                                'uploadId' => 'mock-upload-id',
                                'state' => OrchestratedEventState::ONGOING->value,
                                'type' => OrchestratedEvent::INGESTION->value,
                                'eventTime' => $eventTime->format(DateTimeImmutable::ATOM)
                            ],
                            null
                        ),
                        new EventStateChange(
                            Provider::GITHUB,
                            'mock-identifier',
                            'mock-owner',
                            'mock-repository',
                            2,
                            [
                                'state' => OrchestratedEventState::SUCCESS->value,
                            ],
                            null
                        ),
                        new EventStateChange(
                            Provider::GITHUB,
                            'mock-identifier',
                            'mock-owner',
                            'mock-repository',
                            3,
                            [
                                'state' => OrchestratedEventState::ONGOING->value,
                            ],
                            null
                        ),
                        new EventStateChange(
                            Provider::GITHUB,
                            'mock-identifier',
                            'mock-owner',
                            'mock-repository',
                            4,
                            [
                                'commit' => '2',
                                'state' => OrchestratedEventState::FAILURE->value,
                            ],
                            null
                        )
                    ]
                ),
                new Ingestion(
                    Provider::GITHUB,
                    'mock-owner',
                    'mock-repo',
                    '2',
                    'mock-upload-id',
                    OrchestratedEventState::FAILURE,
                    $eventTime
                )
            ],
            [
                new EventStateChangeCollection(
                    [
                        new EventStateChange(
                            Provider::GITHUB,
                            'mock-identifier',
                            'mock-owner',
                            'mock-repository',
                            1,
                            [
                                'provider' => Provider::GITHUB->value,
                                'owner' => 'mock-owner',
                                'repository' => 'mock-repo',
                                'commit' => '1',
                                'state' => OrchestratedEventState::ONGOING->value,
                                'type' => OrchestratedEvent::JOB->value,
                                'eventTime' => $eventTime->format(DateTimeImmutable::ATOM),
                                'externalId' => 'mock-external-id'
                            ],
                            null
                        ),
                        new EventStateChange(
                            Provider::GITHUB,
                            'mock-identifier',
                            'mock-owner',
                            'mock-repository',
                            2,
                            [
                                'state' => OrchestratedEventState::FAILURE->value,
                                'eventTime' => $eventTime->add(new DateInterval('PT1S'))
                                    ->format(DateTimeImmutable::ATOM),
                            ],
                            null
                        ),
                        new EventStateChange(
                            Provider::GITHUB,
                            'mock-identifier',
                            'mock-owner',
                            'mock-repository',
                            3,
                            [
                                'state' => OrchestratedEventState::SUCCESS->value,
                            ],
                            null
                        )
                    ]
                ),
                new Job(
                    Provider::GITHUB,
                    'mock-owner',
                    'mock-repo',
                    '1',
                    OrchestratedEventState::SUCCESS,
                    $eventTime->add(new DateInterval('PT1S')),
                    'mock-external-id'
                )
            ]
        ];
    }
}
