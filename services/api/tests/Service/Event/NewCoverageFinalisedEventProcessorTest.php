<?php

namespace App\Tests\Service\Event;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use App\Service\Event\NewCoverageFinalisedEventProcessor;
use Bref\Event\EventBridge\EventBridgeEvent;
use DateTimeImmutable;
use Packages\Models\Enum\EventBus\CoverageEvent;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Event\CoverageFinalised;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Serializer\Serializer;

class NewCoverageFinalisedEventProcessorTest extends TestCase
{
    public function testNonMainRefEventProcess(): void
    {
        $coverageFinalised = [
            'provider' => Provider::GITHUB->value,
            'owner' => 'mock-owner',
            'repository' => 'mock-repository',
            'commit' => 'mock-commit',
            'uploadId' => 'mock-uploadId',
            'ref' => 'not-main-ref',
            'parent' => [],
            'tag' => 'mock-tag',
            'coveragePercentage' => 'not-a-float',
            'eventTime' => new DateTimeImmutable()
        ];

        $mockProjectRepository = $this->createMock(ProjectRepository::class);
        $mockProjectRepository->expects($this->never())
            ->method('findOneBy');
        $mockProjectRepository->expects($this->never())
            ->method('save');

        $mockSerializer = $this->createMock(Serializer::class);
        $mockSerializer->expects($this->once())
            ->method('denormalize')
            ->with(
                $coverageFinalised,
                CoverageFinalised::class
            )
            ->willReturn($this->createMock(CoverageFinalised::class));

        $eventProcessor = new NewCoverageFinalisedEventProcessor(
            new NullLogger(),
            $mockProjectRepository,
            $mockSerializer
        );

        $eventProcessor->process(
            new EventBridgeEvent(
                [
                    'detail-type' => CoverageEvent::INGEST_SUCCESS->value,
                    'detail' => $coverageFinalised
                ]
            )
        );
    }

    public function testNoValidProjectEventProcess(): void
    {
        $mockProjectRepository = $this->createMock(ProjectRepository::class);
        $mockProjectRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        $mockProjectRepository->expects($this->never())
            ->method('save');


        $mockSerializer = $this->createMock(Serializer::class);
        $mockSerializer->expects($this->once())
            ->method('denormalize')
            ->with(
                [],
                CoverageFinalised::class
            )
            ->willReturn(
                new CoverageFinalised(
                    Provider::GITHUB,
                    'mock-owner',
                    'mock-repository',
                    'main',
                    'mock-commit',
                    '',
                    99.0,
                    new DateTimeImmutable()
                )
            );

        $eventProcessor = new NewCoverageFinalisedEventProcessor(
            new NullLogger(),
            $mockProjectRepository,
            $mockSerializer
        );

        $eventProcessor->process(
            new EventBridgeEvent(
                [
                    'detail-type' => CoverageEvent::NEW_COVERAGE_FINALISED->value,
                    'detail' => []
                ]
            )
        );
    }

    public function testValidCoverageEventProcess(): void
    {
        $project = $this->createMock(Project::class);

        $mockProjectRepository = $this->createMock(ProjectRepository::class);
        $mockProjectRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn($project);
        $mockProjectRepository->expects($this->once())
            ->method('save')
            ->with($project, true);

        $project->expects($this->once())
            ->method('setCoveragePercentage')
            ->with(99);

        $mockSerializer = $this->createMock(Serializer::class);
        $mockSerializer->expects($this->once())
            ->method('denormalize')
            ->with(
                [],
                CoverageFinalised::class
            )
            ->willReturn(
                new CoverageFinalised(
                    Provider::GITHUB,
                    'mock-owner',
                    'mock-repository',
                    'main',
                    'mock-commit',
                    '',
                    99.0,
                    new DateTimeImmutable()
                )
            );

        $eventProcessor = new NewCoverageFinalisedEventProcessor(
            new NullLogger(),
            $mockProjectRepository,
            $mockSerializer
        );

        $eventProcessor->process(
            new EventBridgeEvent(
                [
                    'detail-type' => CoverageEvent::INGEST_SUCCESS->value,
                    'detail' => []
                ]
            )
        );
    }
}
