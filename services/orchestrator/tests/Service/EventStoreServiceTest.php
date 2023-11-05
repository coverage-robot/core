<?php

namespace App\Tests\Service;

use App\Enum\OrchestratedEvent;
use App\Enum\OrchestratedEventState;
use App\Model\Ingestion;
use App\Model\Job;
use App\Model\OrchestratedEventInterface;
use App\Service\EventStoreService;
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

        $stateChange = $eventStoreService->getStateChange(
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

        $eventStoreService->getStateChange(
            new Ingestion(
                Provider::GITHUB,
                'mock-owner',
                'mock-repo',
                '1',
                OrchestratedEventState::ONGOING
            ),
            new Job(
                Provider::GITHUB,
                'mock-owner',
                'mock-repo',
                '1',
                OrchestratedEventState::ONGOING,
                'mock-external-id'
            )
        );
    }

    #[DataProvider('stateChangesDataProvider')]
    public function testReduceStateChanges(
        array $stateChanges,
        OrchestratedEventInterface $expectedReducedState
    ): void {
        $eventStoreService = new EventStoreService(
            $this->getContainer()->get(SerializerInterface::class)
        );

        $reducedState = $eventStoreService->reduceStateChanges(
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

        $eventStoreService->reduceStateChanges(
            [
                [
                    'some' => 'invalid',
                    'values' => ['x','y'],
                    'type' => OrchestratedEvent::JOB->value,
                ],
                [
                    'new-value' => 'z'
                ]
            ]
        );
    }

    public static function orchestratedEventsDataProvider(): array
    {
        return [
            [
                null,
                new Ingestion(
                    Provider::GITHUB,
                    'mock-owner',
                    'mock-repo',
                    '1',
                    OrchestratedEventState::ONGOING
                ),
                [
                    'provider' => Provider::GITHUB->value,
                    'owner' => 'mock-owner',
                    'repository' => 'mock-repo',
                    'commit' => '1',
                    'state' => OrchestratedEventState::ONGOING->value,
                    'type' => OrchestratedEvent::INGESTION->value
                ]
            ],
            [
                new Ingestion(
                    Provider::GITHUB,
                    'mock-owner',
                    'mock-repo',
                    '1',
                    OrchestratedEventState::ONGOING
                ),
                new Ingestion(
                    Provider::GITHUB,
                    'mock-owner',
                    'mock-repo',
                    '1',
                    OrchestratedEventState::SUCCESS
                ),
                [
                    'state' => OrchestratedEventState::SUCCESS->value
                ]
            ]
        ];
    }

    public function stateChangesDataProvider(): array
    {
        return [
            [
                [
                    [
                        'provider' => Provider::GITHUB->value,
                        'owner' => 'mock-owner',
                        'repository' => 'mock-repo',
                        'commit' => '1',
                        'state' => OrchestratedEventState::ONGOING->value,
                        'type' => OrchestratedEvent::INGESTION->value
                    ],
                    [
                        'state' => OrchestratedEventState::SUCCESS->value,
                    ],
                    [
                        'state' => OrchestratedEventState::FAILURE->value,
                    ],
                    [
                        'commit' => '2',
                    ]
                ],
                new Ingestion(
                    Provider::GITHUB,
                    'mock-owner',
                    'mock-repo',
                    '2',
                    OrchestratedEventState::FAILURE
                )
            ],
            [
                [
                    [
                        'provider' => Provider::GITHUB->value,
                        'owner' => 'mock-owner',
                        'repository' => 'mock-repo',
                        'commit' => '1',
                        'state' => OrchestratedEventState::ONGOING->value,
                        'type' => OrchestratedEvent::JOB->value,
                        'externalId' => 'mock-external-id'
                    ],
                    [
                        'state' => OrchestratedEventState::FAILURE->value,
                    ],
                    [
                        'state' => OrchestratedEventState::SUCCESS->value,
                    ]
                ],
                new Job(
                    Provider::GITHUB,
                    'mock-owner',
                    'mock-repo',
                    '1',
                    OrchestratedEventState::SUCCESS,
                    'mock-external-id'
                )
            ]
        ];
    }
}
