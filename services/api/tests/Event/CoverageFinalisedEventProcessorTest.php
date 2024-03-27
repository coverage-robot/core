<?php

namespace App\Tests\Event;

use App\Client\DynamoDbClientInterface;
use App\Entity\Project;
use App\Event\CoverageFinalisedEventProcessor;
use App\Repository\ProjectRepository;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Model\CoverageFinalised;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class CoverageFinalisedEventProcessorTest extends TestCase
{
    public function testNonMainRefEventProcess(): void
    {
        $coverageFinalised = new CoverageFinalised(
            provider: Provider::GITHUB,
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'not-main-ref',
            commit: 'mock-commit',
            coveragePercentage: 99.0,
            pullRequest: 12,
            baseRef: 'main',
            baseCommit: 'mock-main-commit'
        );

        $mockProjectRepository = $this->createMock(ProjectRepository::class);
        $mockProjectRepository->expects($this->never())
            ->method('findOneBy');
        $mockProjectRepository->expects($this->never())
            ->method('save');

        $eventProcessor = new CoverageFinalisedEventProcessor(
            new NullLogger(),
            $mockProjectRepository,
            $this->createMock(DynamoDbClientInterface::class)
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
            $mockProjectRepository,
            $this->createMock(DynamoDbClientInterface::class)
        );

        $eventProcessor->process(new CoverageFinalised(
            provider: Provider::GITHUB,
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'main',
            commit: 'mock-commit',
            coveragePercentage: 99.0,
            pullRequest: 12,
            baseRef: 'main',
            baseCommit: 'mock-main-commit'
        ));
    }

    public function testValidCoverageEventProcess(): void
    {
        $project = new Project();
        $project->setCoveragePercentage(0);

        $mockProjectRepository = $this->createMock(ProjectRepository::class);
        $mockProjectRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn($project);
        $mockProjectRepository->expects($this->once())
            ->method('save')
            ->with(
                self::callback(static fn (Project $project): bool => $project->getCoveragePercentage() === 99.0),
                true
            );

        $eventProcessor = new CoverageFinalisedEventProcessor(
            new NullLogger(),
            $mockProjectRepository,
            $this->createMock(DynamoDbClientInterface::class)
        );

        $eventProcessor->process(
            new CoverageFinalised(
                provider: Provider::GITHUB,
                owner: 'mock-owner',
                repository: 'mock-repository',
                ref: 'main',
                commit: 'mock-commit',
                coveragePercentage: 99.0,
                pullRequest: 12,
                baseRef: 'main',
                baseCommit: 'mock-main-commit'
            )
        );
    }
}
