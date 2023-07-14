<?php

namespace App\Tests\Service\Event;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use App\Service\Event\AnalyseSuccessEventProcessor;
use Bref\Event\EventBridge\EventBridgeEvent;
use Packages\Models\Enum\EventBus\CoverageEvent;
use Packages\Models\Enum\Provider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class IngestSuccessEventProcessorTest extends TestCase
{
    public function testMalformedEventProcess(): void
    {
        $mockProjectRepository = $this->createMock(ProjectRepository::class);
        $mockProjectRepository->expects($this->never())
            ->method('findOneBy');
        $mockProjectRepository->expects($this->never())
            ->method('save');

        $eventProcessor = new AnalyseSuccessEventProcessor(
            new NullLogger(),
            $mockProjectRepository
        );

        $eventProcessor->process(
            new EventBridgeEvent(
                [
                    'detail-type' => CoverageEvent::INGEST_SUCCESS->value,
                    'detail' => json_encode([
                        'upload' => [
                            'provider' => Provider::GITHUB->value,
                            'owner' => 'mock-owner',
                            'repository' => 'mock-repository',
                            'commit' => 'mock-commit',
                            'uploadId' => 'mock-uploadId',
                            'ref' => 'not-main-ref',
                            'parent' => [],
                            'tag' => 'mock-tag',
                        ],
                        'coveragePercentage' => 'not-a-float'
                    ])
                ]
            )
        );
    }

    public function testNonMainRefEventProcess(): void
    {
        $mockProjectRepository = $this->createMock(ProjectRepository::class);
        $mockProjectRepository->expects($this->never())
            ->method('findOneBy');
        $mockProjectRepository->expects($this->never())
            ->method('save');

        $eventProcessor = new AnalyseSuccessEventProcessor(
            new NullLogger(),
            $mockProjectRepository
        );

        $eventProcessor->process(
            new EventBridgeEvent(
                [
                    'detail-type' => CoverageEvent::INGEST_SUCCESS->value,
                    'detail' => json_encode([
                        'upload' => [
                            'provider' => Provider::GITHUB->value,
                            'owner' => 'mock-owner',
                            'repository' => 'mock-repository',
                            'commit' => 'mock-commit',
                            'uploadId' => 'mock-uploadId',
                            'ref' => 'not-main-ref',
                            'parent' => [],
                            'tag' => 'mock-tag',
                        ],
                        'coveragePercentage' => 99
                    ])
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

        $eventProcessor = new AnalyseSuccessEventProcessor(
            new NullLogger(),
            $mockProjectRepository
        );

        $eventProcessor->process(
            new EventBridgeEvent(
                [
                    'detail-type' => CoverageEvent::INGEST_SUCCESS->value,
                    'detail' => [
                        'upload' => [
                            'provider' => Provider::GITHUB->value,
                            'owner' => 'mock-owner',
                            'repository' => 'mock-repository',
                            'commit' => 'mock-commit',
                            'uploadId' => 'mock-uploadId',
                            'ref' => 'main',
                            'parent' => [],
                            'tag' => 'mock-tag',
                        ],
                        'coveragePercentage' => 99
                    ]
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

        $eventProcessor = new AnalyseSuccessEventProcessor(
            new NullLogger(),
            $mockProjectRepository
        );

        $eventProcessor->process(
            new EventBridgeEvent(
                [
                    'detail-type' => CoverageEvent::INGEST_SUCCESS->value,
                    'detail' => [
                        'upload' => [
                            'provider' => Provider::GITHUB->value,
                            'owner' => 'mock-owner',
                            'repository' => 'mock-repository',
                            'commit' => 'mock-commit',
                            'uploadId' => 'mock-uploadId',
                            'ref' => 'main',
                            'parent' => [],
                            'tag' => 'mock-tag',
                        ],
                        'coveragePercentage' => 99
                    ]
                ]
            )
        );
    }
}
