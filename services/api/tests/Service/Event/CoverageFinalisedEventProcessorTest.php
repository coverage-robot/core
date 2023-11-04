<?php

namespace App\Tests\Service\Event;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use App\Service\Event\CoverageFinalisedEventProcessor;
use DateTimeImmutable;
use Packages\Event\Model\CoverageFinalised;
use Packages\Models\Enum\Provider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class CoverageFinalisedEventProcessorTest extends TestCase
{
    public function testNonMainRefEventProcess(): void
    {
        $coverageFinalised = new CoverageFinalised(
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'not-main-ref',
            'mock-commit',
            '',
            99.0,
            new DateTimeImmutable()
        );

        $mockProjectRepository = $this->createMock(ProjectRepository::class);
        $mockProjectRepository->expects($this->never())
            ->method('findOneBy');
        $mockProjectRepository->expects($this->never())
            ->method('save');

        $eventProcessor = new CoverageFinalisedEventProcessor(
            new NullLogger(),
            $mockProjectRepository
        );

        $eventProcessor->process($coverageFinalised);
    }

    public function testNoValidProjectEventProcess(): void
    {
        $mockProjectRepository = $this->createMock(ProjectRepository::class);
        $mockProjectRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        $mockProjectRepository->expects($this->never())
            ->method('save');

        $eventProcessor = new CoverageFinalisedEventProcessor(
            new NullLogger(),
            $mockProjectRepository
        );

        $eventProcessor->process(new CoverageFinalised(
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'main',
            'mock-commit',
            '',
            99.0,
            new DateTimeImmutable()
        ));
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

        $eventProcessor = new CoverageFinalisedEventProcessor(
            new NullLogger(),
            $mockProjectRepository
        );

        $eventProcessor->process(
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
    }
}
