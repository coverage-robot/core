<?php

namespace App\Tests\Service\Event;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use App\Service\Event\CoverageFinalisedEventProcessor;
use Bref\Event\EventBridge\EventBridgeEvent;
use DateTimeImmutable;
use Packages\Event\Enum\Event;
use Packages\Event\Model\CoverageFinalised;
use Packages\Models\Enum\Provider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Serializer\Serializer;

class CoverageFinalisedEventProcessorTest extends TestCase
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

        $eventProcessor = new CoverageFinalisedEventProcessor(
            new NullLogger(),
            $mockProjectRepository,
            $mockSerializer
        );

        $eventProcessor->process(
            new EventBridgeEvent(
                [
                    'detail-type' => Event::INGEST_SUCCESS->value,
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

        $eventProcessor = new CoverageFinalisedEventProcessor(
            new NullLogger(),
            $mockProjectRepository,
            $mockSerializer
        );

        $eventProcessor->process(
            new EventBridgeEvent(
                [
                    'detail-type' => Event::COVERAGE_FINALISED->value,
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

        $eventProcessor = new CoverageFinalisedEventProcessor(
            new NullLogger(),
            $mockProjectRepository,
            $mockSerializer
        );

        $eventProcessor->process(
            new EventBridgeEvent(
                [
                    'detail-type' => Event::INGEST_SUCCESS->value,
                    'detail' => []
                ]
            )
        );
    }
}
