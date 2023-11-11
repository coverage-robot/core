<?php

namespace App\Tests\Service;

use App\Enum\OrchestratedEvent;
use App\Enum\OrchestratedEventState;
use App\Model\EventStateChange;
use App\Model\EventStateChangeCollection;
use App\Model\Ingestion;
use App\Model\Job;
use App\Model\OrchestratedEventInterface;
use App\Service\EventStoreService;
use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use Packages\Models\Enum\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
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
            $this->getContainer()->get(SerializerInterface::class)
        );

        $stateChange = $eventStoreService->getStateChangeForEvent(
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
            $this->getContainer()->get(SerializerInterface::class)
        );

        $this->expectException(InvalidArgumentException::class);

        $eventStoreService->getStateChangeForEvent(
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
            $this->getContainer()->get(SerializerInterface::class)
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
            $this->getContainer()->get(SerializerInterface::class)
        );

        $this->expectException(MissingConstructorArgumentsException::class);

        $eventStoreService->reduceStateChangesToEvent(
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
                        0
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
                        0
                    )
                ]
            )
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
                            0
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
                            0
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
                            0
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
                            0
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
                            0
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
                            0
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
                            0
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
