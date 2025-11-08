<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Client\DynamoDbClientInterface;
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
use Iterator;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Exception;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

final class EventStoreServiceTest extends KernelTestCase
{
    /**
     * @param array<string, string> $expectedStateChange
     * @throws Exception
     */
    #[DataProvider('orchestratedEventsDataProvider')]
    public function testCalculatingStateChangeBetweenEvents(
        ?OrchestratedEventInterface $currentState,
        OrchestratedEventInterface $newState,
        array $expectedStateChange
    ): void {
        $eventStoreService = new EventStoreService(
            $this->getContainer()->get(SerializerInterface::class),
            $this->createMock(DynamoDbClientInterface::class),
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
            $this->createMock(DynamoDbClientInterface::class),
            new NullLogger()
        );

        $this->expectException(InvalidArgumentException::class);

        $eventStoreService->getStateChangesBetweenEvent(
            new Ingestion(
                Provider::GITHUB,
                'mock-project-id',
                'mock-owner',
                'mock-repo',
                '1',
                'mock-upload-id',
                OrchestratedEventState::ONGOING,
                new DateTimeImmutable()
            ),
            new Job(
                Provider::GITHUB,
                'mock-project-id',
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
            $this->createMock(DynamoDbClientInterface::class),
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
            $this->createMock(DynamoDbClientInterface::class),
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
                        ]
                    ),
                    new EventStateChange(
                        Provider::GITHUB,
                        'mock-identifier',
                        'mock-owner',
                        'mock-repository',
                        2,
                        [
                            'new-value' => 'z'
                        ]
                    )
                ]
            )
        );

        $this->assertNotInstanceOf(OrchestratedEventInterface::class, $previousEvent);
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
            'mock-project-id',
            'mock-owner',
            'mock-repo',
            '1',
            'mock-upload-id',
            OrchestratedEventState::SUCCESS,
            $eventTime
        );

        $mockDynamoDbClient = $this->createMock(DynamoDbClientInterface::class);
        $mockDynamoDbClient->expects($this->once())
            ->method('getStateChangesForEvent')
            ->willReturn([
                [
                    'provider' => new AttributeValue(['S' => Provider::GITHUB->value]),
                    'version' => new AttributeValue(['N' => 1]),
                    'event' => new AttributeValue(['S' => $serializer->serialize($event, 'json')]),
                    'eventTime' => new AttributeValue(['N' => $eventTime->getMicrosecond()]),
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

        $this->assertSame(
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
            'mock-project-id',
            'mock-owner',
            'mock-repo',
            '1',
            'mock-upload-id',
            OrchestratedEventState::ONGOING,
            $eventTime
        );

        $completeEvent = new Ingestion(
            Provider::GITHUB,
            'mock-project-id',
            'mock-owner',
            'mock-repo',
            '1',
            'mock-upload-id',
            OrchestratedEventState::SUCCESS,
            $eventTime
        );

        $mockDynamoDbClient = $this->createMock(DynamoDbClientInterface::class);
        $mockDynamoDbClient->expects($this->once())
            ->method('getStateChangesForEvent')
            ->willReturn([
                [
                    'provider' => new AttributeValue(['S' => Provider::GITHUB->value]),
                    'version' => new AttributeValue(['N' => 1]),
                    'event' => new AttributeValue(['S' => $serializer->serialize($ongoingEvent, 'json')]),
                    'eventTime' => new AttributeValue(['N' => $eventTime->getMicrosecond()]),
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

        $this->assertSame(
            2,
            $stateChange->getVersion()
        );
        $this->assertSame(
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

        $mockDynamoDbClient = $this->createMock(DynamoDbClientInterface::class);
        $mockDynamoDbClient->expects($this->once())
            ->method('getEventStateChangesForCommit')
            ->willReturn([
                [
                    'provider' => new AttributeValue(['S' => Provider::GITHUB->value]),
                    'version' => new AttributeValue(['N' => 1]),
                    'identifier' => new AttributeValue(['S' => 'mock-identifier-1']),
                    'event' => new AttributeValue(['S' => '{"mock": "value"}']),
                    'eventTime' => new AttributeValue(['N' => '1']),
                ],
                [
                    'provider' => new AttributeValue(['S' => Provider::GITHUB->value]),
                    'version' => new AttributeValue(['N' => 1]),
                    'identifier' => new AttributeValue(['S' => 'mock-identifier-2']),
                    'event' => new AttributeValue(['S' => '{"mock": "value-2"}']),
                    'eventTime' => new AttributeValue(['N' => '1']),
                ],
                [
                    'provider' => new AttributeValue(['S' => Provider::GITHUB->value]),
                    'version' => new AttributeValue(['N' => 2]),
                    'identifier' => new AttributeValue(['S' => 'mock-identifier-2']),
                    'event' => new AttributeValue(['S' => '{"mock": "value-3"}']),
                    'eventTime' => new AttributeValue(['N' => '1']),
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
                    new DateTimeImmutable('1970-01-01T00:00:01.000000+0000')
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
                    new DateTimeImmutable('1970-01-01T00:00:01.000000+0000')
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
                    new DateTimeImmutable('1970-01-01T00:00:01.000000+0000')
                )
            ],
            $stateChanges['mock-identifier-2']->getEvents()
        );
    }

    /**
     * @return Iterator<list{ Ingestion|null, Ingestion, array<string, mixed> }>
     */
    public static function orchestratedEventsDataProvider(): Iterator
    {
        $eventTime = new DateTimeImmutable('2021-01-01T00:00:00+00:00');
        yield [
            null,
            new Ingestion(
                Provider::GITHUB,
                'mock-project-id',
                'mock-owner',
                'mock-repo',
                '1',
                'mock-upload-id',
                OrchestratedEventState::ONGOING,
                $eventTime
            ),
            [
                'provider' => Provider::GITHUB->value,
                'projectId' => 'mock-project-id',
                'owner' => 'mock-owner',
                'repository' => 'mock-repo',
                'commit' => '1',
                'uploadId' => 'mock-upload-id',
                'state' => OrchestratedEventState::ONGOING->value,
                'type' => OrchestratedEvent::INGESTION->value,
                'eventTime' => $eventTime->format(DateTimeImmutable::ATOM)
            ]
        ];

        yield [
            new Ingestion(
                Provider::GITHUB,
                'mock-project-id',
                'mock-owner',
                'mock-repo',
                '1',
                'mock-upload-id',
                OrchestratedEventState::ONGOING,
                $eventTime
            ),
            new Ingestion(
                Provider::GITHUB,
                'mock-project-id',
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
        ];

        yield [
            new Ingestion(
                Provider::GITHUB,
                'mock-project-id',
                'mock-owner',
                'mock-repo',
                '1',
                'mock-upload-id',
                OrchestratedEventState::ONGOING,
                $eventTime
            ),
            new Ingestion(
                Provider::GITHUB,
                'mock-project-id',
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
        ];
    }

    /**
     * @return Iterator<array<array<int, mixed>, mixed>>
     */
    public static function stateChangesDataProvider(): Iterator
    {
        $eventTime = new DateTimeImmutable('2021-01-01T00:00:00+00:00');
        yield [
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
                            'projectId' => 'mock-project-id',
                            'owner' => 'mock-owner',
                            'repository' => 'mock-repo',
                            'commit' => '1',
                            'uploadId' => 'mock-upload-id',
                            'state' => OrchestratedEventState::ONGOING->value,
                            'type' => OrchestratedEvent::INGESTION->value,
                            'eventTime' => $eventTime->format(DateTimeImmutable::ATOM)
                        ]
                    ),
                    new EventStateChange(
                        Provider::GITHUB,
                        'mock-identifier',
                        'mock-owner',
                        'mock-repository',
                        2,
                        [
                            'state' => OrchestratedEventState::SUCCESS->value,
                        ]
                    ),
                    new EventStateChange(
                        Provider::GITHUB,
                        'mock-identifier',
                        'mock-owner',
                        'mock-repository',
                        3,
                        [
                            'state' => OrchestratedEventState::ONGOING->value,
                        ]
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
                        ]
                    )
                ]
            ),
            new Ingestion(
                Provider::GITHUB,
                'mock-project-id',
                'mock-owner',
                'mock-repo',
                '2',
                'mock-upload-id',
                OrchestratedEventState::FAILURE,
                $eventTime
            )
        ];

        yield [
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
                            'projectId' => 'mock-project-id',
                            'owner' => 'mock-owner',
                            'repository' => 'mock-repo',
                            'commit' => '1',
                            'state' => OrchestratedEventState::ONGOING->value,
                            'type' => OrchestratedEvent::JOB->value,
                            'eventTime' => $eventTime->format(DateTimeImmutable::ATOM),
                            'externalId' => 'mock-external-id'
                        ]
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
                        ]
                    ),
                    new EventStateChange(
                        Provider::GITHUB,
                        'mock-identifier',
                        'mock-owner',
                        'mock-repository',
                        3,
                        [
                            'state' => OrchestratedEventState::SUCCESS->value,
                        ]
                    )
                ]
            ),
            new Job(
                Provider::GITHUB,
                'mock-project-id',
                'mock-owner',
                'mock-repo',
                '1',
                OrchestratedEventState::SUCCESS,
                $eventTime->add(new DateInterval('PT1S')),
                'mock-external-id'
            )
        ];
    }
}
