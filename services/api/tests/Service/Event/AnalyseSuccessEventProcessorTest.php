<?php

namespace App\Tests\Service\Event;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use App\Service\Event\AnalysisOnNewUploadSuccessEventProcessor;
use App\Tests\Mock\Factory\MockSerializerFactory;
use Bref\Event\EventBridge\EventBridgeEvent;
use Packages\Models\Enum\EventBus\CoverageEvent;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Event\Upload;
use Packages\Models\Model\Tag;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Serializer\SerializerInterface;

class AnalyseSuccessEventProcessorTest extends TestCase
{
    public function testMalformedEventProcess(): void
    {
        $mockProjectRepository = $this->createMock(ProjectRepository::class);
        $mockProjectRepository->expects($this->never())
            ->method('findOneBy');
        $mockProjectRepository->expects($this->never())
            ->method('save');

        $mockSerializer = $this->createMock(SerializerInterface::class);
        $mockSerializer->expects($this->never())
            ->method('deserialize');

        $eventProcessor = new AnalysisOnNewUploadSuccessEventProcessor(
            new NullLogger(),
            $mockProjectRepository,
            $mockSerializer
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
                            'ref' => 'not-main-ref',
                            'parent' => [],
                            'tag' => 'mock-tag',
                        ],
                        'coveragePercentage' => 'not-a-float'
                    ]
                ]
            )
        );
    }

    public function testNonMainRefEventProcess(): void
    {
        $upload = [
            'provider' => Provider::GITHUB->value,
            'owner' => 'mock-owner',
            'repository' => 'mock-repository',
            'commit' => 'mock-commit',
            'uploadId' => 'mock-uploadId',
            'ref' => 'not-main-ref',
            'parent' => [],
            'tag' => 'mock-tag',
        ];

        $mockProjectRepository = $this->createMock(ProjectRepository::class);
        $mockProjectRepository->expects($this->never())
            ->method('findOneBy');
        $mockProjectRepository->expects($this->never())
            ->method('save');

        $mockSerializer = MockSerializerFactory::getMock(
            $this,
            deserializeMap: [
                [
                    $upload,
                    Upload::class,
                    'json',
                    [],
                    $this->createMock(Upload::class)
                ]
            ]
        );

        $eventProcessor = new AnalysisOnNewUploadSuccessEventProcessor(
            new NullLogger(),
            $mockProjectRepository,
            $mockSerializer
        );

        $eventProcessor->process(
            new EventBridgeEvent(
                [
                    'detail-type' => CoverageEvent::INGEST_SUCCESS->value,
                    'detail' => [
                        'upload' => $upload,
                        'coveragePercentage' => 99
                    ]
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

        $mockSerializer = MockSerializerFactory::getMock(
            $this,
            [],
            [
                [
                    [],
                    Upload::class,
                    'json',
                    [],
                    new Upload(
                        '',
                        Provider::GITHUB,
                        'mock-owner',
                        'mock-repository',
                        'mock-commit',
                        [],
                        'main',
                        '',
                        '',
                        new Tag('mock-tag', 'mock-commit')
                    )
                ]
            ]
        );

        $eventProcessor = new AnalysisOnNewUploadSuccessEventProcessor(
            new NullLogger(),
            $mockProjectRepository,
            $mockSerializer
        );

        $eventProcessor->process(
            new EventBridgeEvent(
                [
                    'detail-type' => CoverageEvent::INGEST_SUCCESS->value,
                    'detail' => [
                        'upload' => [],
                        'coveragePercentage' => 99
                    ]
                ]
            )
        );
    }

    public function testValidCoverageEventProcess(): void
    {
        $project = $this->createMock(Project::class);
        $upload = [
            'provider' => Provider::GITHUB->value,
            'owner' => 'mock-owner',
            'repository' => 'mock-repository',
            'commit' => 'mock-commit',
            'uploadId' => 'mock-uploadId',
            'ref' => 'main',
            'parent' => [],
            'tag' => 'mock-tag',
        ];

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

        $mockSerializer = MockSerializerFactory::getMock(
            $this,
            [],
            [
                [
                    $upload,
                    Upload::class,
                    'json',
                    [],
                    new Upload(
                        '',
                        Provider::GITHUB,
                        'mock-owner',
                        'mock-repository',
                        'mock-commit',
                        [],
                        'main',
                        '',
                        '',
                        new Tag('mock-tag', 'mock-commit')
                    )
                ]
            ]
        );

        $eventProcessor = new AnalysisOnNewUploadSuccessEventProcessor(
            new NullLogger(),
            $mockProjectRepository,
            $mockSerializer
        );

        $eventProcessor->process(
            new EventBridgeEvent(
                [
                    'detail-type' => CoverageEvent::INGEST_SUCCESS->value,
                    'detail' => [
                        'upload' => $upload,
                        'coveragePercentage' => 99
                    ]
                ]
            )
        );
    }
}
