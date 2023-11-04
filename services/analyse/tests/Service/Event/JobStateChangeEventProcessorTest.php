<?php

namespace App\Tests\Service\Event;

use App\Client\EventBridgeEventClient;
use App\Client\SqsMessageClient;
use App\Enum\EnvironmentVariable;
use App\Model\PublishableCoverageDataInterface;
use App\Query\Result\TagCoverageCollectionQueryResult;
use App\Query\Result\TagCoverageQueryResult;
use App\Service\CoverageAnalyserService;
use App\Service\Event\JobStateChangeEventProcessor;
use App\Tests\Mock\Factory\MockEnvironmentServiceFactory;
use DateTimeImmutable;
use Github\Api\Repository\Checks\CheckRuns;
use Packages\Clients\Client\Github\GithubAppInstallationClient;
use Packages\Event\Enum\Event;
use Packages\Event\Model\JobStateChange;
use Packages\Models\Enum\Environment;
use Packages\Models\Enum\JobState;
use Packages\Models\Enum\Provider;
use Packages\Models\Enum\PublishableCheckRunStatus;
use Packages\Models\Model\PublishableMessage\PublishableCheckRunMessage;
use Packages\Models\Model\PublishableMessage\PublishableMessageCollection;
use Packages\Models\Model\Tag;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

class JobStateChangeEventProcessorTest extends KernelTestCase
{
    public function testProcessFirstJob(): void
    {
        $jobStateChange = new JobStateChange(
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-ref',
            'mock-commit',
            'mock-pull-request',
            'mock-id',
            0,
            JobState::IN_PROGRESS,
            JobState::IN_PROGRESS,
            true,
            new DateTimeImmutable('2021-01-01T00:00:00+00:00')
        );

        $mockPublishableCoverageData = $this->createMock(PublishableCoverageDataInterface::class);
        $mockPublishableCoverageData->expects($this->never())
            ->method('getCoveragePercentage');

        $mockCoverageAnalysisService = $this->createMock(CoverageAnalyserService::class);
        $mockCoverageAnalysisService->expects($this->once())
            ->method('analyse')
            ->willReturn($mockPublishableCoverageData);

        $mockGithubAppInstallationClient = $this->createMock(GithubAppInstallationClient::class);
        $mockGithubAppInstallationClient->expects($this->never())
            ->method('checkRuns');

        $mockSqsMessageClient = $this->createMock(SqsMessageClient::class);
        $mockSqsMessageClient->expects($this->once())
            ->method('queuePublishableMessage')
            ->with(
                self::callback(
                    function (PublishableCheckRunMessage $message) {
                        $this->assertEquals(
                            PublishableCheckRunStatus::IN_PROGRESS,
                            $message->getStatus()
                        );
                        $this->assertCount(
                            0,
                            $message->getAnnotations()
                        );
                        return true;
                    }
                )
            )
            ->willReturn(true);

        $mockEventBridgeEventClient = $this->createMock(EventBridgeEventClient::class);

        $jobStateChangeEventProcessor = new JobStateChangeEventProcessor(
            new NullLogger(),
            $this->getContainer()->get(SerializerInterface::class),
            $mockCoverageAnalysisService,
            $mockGithubAppInstallationClient,
            MockEnvironmentServiceFactory::getMock(
                $this,
                Environment::TESTING,
                [
                    EnvironmentVariable::GITHUB_APP_ID->value => 'mock-github-app-id',
                ]
            ),
            $mockSqsMessageClient,
            $mockEventBridgeEventClient,
        );

        $jobStateChangeEventProcessor->process($jobStateChange);
    }

    public function testProcessLastJob(): void
    {
        $jobStateChange = new JobStateChange(
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-ref',
            'mock-commit',
            'mock-pull-request',
            'mock-id',
            1,
            JobState::COMPLETED,
            JobState::COMPLETED,
            true,
            new DateTimeImmutable('2021-01-01T00:00:00+00:00')
        );

        $mockPublishableCoverageData = $this->createMock(PublishableCoverageDataInterface::class);
        $mockPublishableCoverageData->expects($this->atLeastOnce())
            ->method('getCoveragePercentage')
            ->willReturn(97.0);
        $mockPublishableCoverageData->expects($this->atLeastOnce())
            ->method('getTagCoverage')
            ->willReturn(
                new TagCoverageCollectionQueryResult(
                    [
                        new TagCoverageQueryResult(
                            new Tag('mock-tag', 'mock-commit'),
                            100,
                            1,
                            1,
                            0,
                            0
                        ),
                        new TagCoverageQueryResult(
                            new Tag('mock-tag-2', 'mock-commit-2'),
                            50,
                            2,
                            1,
                            0,
                            0
                        )
                    ]
                )
            );

        $mockCoverageAnalysisService = $this->createMock(CoverageAnalyserService::class);
        $mockCoverageAnalysisService->expects($this->once())
            ->method('analyse')
            ->willReturn($mockPublishableCoverageData);

        $mockCheckRunsApi = $this->createMock(CheckRuns::class);
        $mockCheckRunsApi->expects($this->once())
            ->method('allForReference')
            ->willReturn([
                'check_runs' => [
                    [
                        'id' => 'different-job',
                        'completed_at' => '2023-02-01T00:00:00+00:00',
                        'status' => 'completed',
                        'app' => [
                            'id' => 'github-app',
                        ],
                    ],
                    [
                        'id' => 'mock-id',
                        'completed_at' => '2023-03-01T00:00:00+00:00',
                        'status' => 'completed',
                        'app' => [
                            'id' => 'github-app',
                        ],
                    ]
                ]
            ]);

        $mockGithubAppInstallationClient = $this->createMock(GithubAppInstallationClient::class);
        $mockGithubAppInstallationClient->expects($this->once())
            ->method('checkRuns')
            ->willReturn($mockCheckRunsApi);

        $mockSqsMessageClient = $this->createMock(SqsMessageClient::class);
        $mockSqsMessageClient->expects($this->once())
            ->method('queuePublishableMessage')
            ->with(
                self::callback(
                    function (PublishableMessageCollection $message) {
                        $this->assertCount(
                            2,
                            $message->getMessages()
                        );
                        $this->assertEquals(
                            [
                                [
                                    'type' => 'TAG_COVERAGE',
                                    'tag' => [

                                        'name' => 'mock-tag',
                                        'commit' => 'mock-commit'
                                    ],
                                    'coveragePercentage' => 100.0,
                                    'lines' => 1,
                                    'covered' => 1,
                                    'partial' => 0,
                                    'uncovered' => 0,
                                ],
                                [
                                    'type' => 'TAG_COVERAGE',
                                    'tag' => [
                                        'name' => 'mock-tag-2',
                                        'commit' => 'mock-commit-2',
                                    ],
                                    'coveragePercentage' => 50.0,
                                    'lines' => 2,
                                    'covered' => 1,
                                    'partial' => 0,
                                    'uncovered' => 0,
                                ]
                            ],
                            $message->getMessages()[0]->getTagCoverage()
                        );
                        $this->assertEquals(
                            PublishableCheckRunStatus::SUCCESS,
                            $message->getMessages()[1]->getStatus()
                        );
                        return true;
                    }
                )
            )
            ->willReturn(true);

        $mockEventBridgeEventClient = $this->createMock(EventBridgeEventClient::class);

        $jobStateChangeEventProcessor = new JobStateChangeEventProcessor(
            new NullLogger(),
            $this->getContainer()->get(SerializerInterface::class),
            $mockCoverageAnalysisService,
            $mockGithubAppInstallationClient,
            MockEnvironmentServiceFactory::getMock(
                $this,
                Environment::TESTING,
                [
                    EnvironmentVariable::GITHUB_APP_ID->value => 'mock-github-app-id',
                ]
            ),
            $mockSqsMessageClient,
            $mockEventBridgeEventClient,
        );

        $jobStateChangeEventProcessor->process($jobStateChange);
    }

    public function testProcessCompetingLastJobs(): void
    {
        $jobStateChange = new JobStateChange(
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-ref',
            'mock-commit',
            'mock-pull-request',
            'mock-id',
            1,
            JobState::COMPLETED,
            JobState::COMPLETED,
            true,
            new DateTimeImmutable('2021-01-01T00:00:00+00:00')
        );

        $mockPublishableCoverageData = $this->createMock(PublishableCoverageDataInterface::class);
        $mockPublishableCoverageData->expects($this->never())
            ->method('getCoveragePercentage');
        $mockPublishableCoverageData->expects($this->never())
            ->method('getTagCoverage');

        $mockCoverageAnalysisService = $this->createMock(CoverageAnalyserService::class);
        $mockCoverageAnalysisService->expects($this->once())
            ->method('analyse')
            ->willReturn($mockPublishableCoverageData);

        $mockCheckRunsApi = $this->createMock(CheckRuns::class);
        $mockCheckRunsApi->expects($this->once())
            ->method('allForReference')
            ->willReturn([
                'check_runs' => [
                    [
                        'id' => 'different-job',
                        'completed_at' => '2023-02-01T00:00:00+00:00',
                        'status' => 'completed',
                        'app' => [
                            'id' => 'github-app',
                        ],
                    ],
                    [
                        'id' => 'mock-id',
                        'completed_at' => '2023-03-01T00:00:00+00:00',
                        'status' => 'completed',
                        'app' => [
                            'id' => 'github-app',
                        ],
                    ],
                    [
                        'id' => 'a-competing-job',
                        'completed_at' => '2023-03-01T00:00:05+00:00',
                        'status' => 'completed',
                        'app' => [
                            'id' => 'github-app',
                        ],
                    ],
                ]
            ]);

        $mockGithubAppInstallationClient = $this->createMock(GithubAppInstallationClient::class);
        $mockGithubAppInstallationClient->expects($this->once())
            ->method('checkRuns')
            ->willReturn($mockCheckRunsApi);

        $mockSqsMessageClient = $this->createMock(SqsMessageClient::class);
        $mockSqsMessageClient->expects($this->never())
            ->method('queuePublishableMessage');

        $mockEventBridgeEventClient = $this->createMock(EventBridgeEventClient::class);

        $jobStateChangeEventProcessor = new JobStateChangeEventProcessor(
            new NullLogger(),
            $this->getContainer()->get(SerializerInterface::class),
            $mockCoverageAnalysisService,
            $mockGithubAppInstallationClient,
            MockEnvironmentServiceFactory::getMock(
                $this,
                Environment::TESTING,
                [
                    EnvironmentVariable::GITHUB_APP_ID->value => 'mock-github-app-id',
                ]
            ),
            $mockSqsMessageClient,
            $mockEventBridgeEventClient,
        );

        $jobStateChangeEventProcessor->process($jobStateChange);
    }

    public function testProcessCompletedJobInMiddleOfSuite(): void
    {
        $jobStateChange = new JobStateChange(
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-ref',
            'mock-commit',
            'mock-pull-request',
            'mock-id',
            1,
            JobState::COMPLETED,
            JobState::IN_PROGRESS,
            true,
            new DateTimeImmutable('2021-01-01T00:00:00+00:00')
        );

        $mockPublishableCoverageData = $this->createMock(PublishableCoverageDataInterface::class);
        $mockPublishableCoverageData->expects($this->never())
            ->method('getCoveragePercentage');
        $mockPublishableCoverageData->expects($this->never())
            ->method('getTagCoverage');

        $mockCoverageAnalysisService = $this->createMock(CoverageAnalyserService::class);
        $mockCoverageAnalysisService->expects($this->once())
            ->method('analyse')
            ->willReturn($mockPublishableCoverageData);


        $mockCheckRunsApi = $this->createMock(CheckRuns::class);
        $mockCheckRunsApi->expects($this->once())
            ->method('allForReference')
            ->willReturn([
                'check_runs' => [
                    [
                        'id' => 'different-job',
                        'completed_at' => '2023-02-01T00:00:00+00:00',
                        'status' => 'completed',
                        'app' => [
                            'id' => 'github-app',
                        ],
                    ],
                    [
                        'id' => 'mock-id',
                        'completed_at' => '2023-03-01T00:00:00+00:00',
                        'status' => 'completed',
                        'app' => [
                            'id' => 'github-app',
                        ],
                    ],
                    [
                        'id' => 'a-competing-job',
                        'completed_at' => '2023-03-01T00:00:05+00:00',
                        'status' => 'in_progress',
                        'app' => [
                            'id' => 'github-app',
                        ],
                    ],
                ]
            ]);

        $mockGithubAppInstallationClient = $this->createMock(GithubAppInstallationClient::class);
        $mockGithubAppInstallationClient->expects($this->once())
            ->method('checkRuns')
            ->willReturn($mockCheckRunsApi);

        $mockSqsMessageClient = $this->createMock(SqsMessageClient::class);
        $mockSqsMessageClient->expects($this->never())
            ->method('queuePublishableMessage');

        $mockEventBridgeEventClient = $this->createMock(EventBridgeEventClient::class);

        $jobStateChangeEventProcessor = new JobStateChangeEventProcessor(
            new NullLogger(),
            $this->getContainer()->get(SerializerInterface::class),
            $mockCoverageAnalysisService,
            $mockGithubAppInstallationClient,
            MockEnvironmentServiceFactory::getMock(
                $this,
                Environment::TESTING,
                [
                    EnvironmentVariable::GITHUB_APP_ID->value => 'mock-github-app-id',
                ]
            ),
            $mockSqsMessageClient,
            $mockEventBridgeEventClient,
        );

        $jobStateChangeEventProcessor->process($jobStateChange);
    }

    public function testProcessorEvent(): void
    {
        $this->assertEquals(
            Event::JOB_STATE_CHANGE->value,
            JobStateChangeEventProcessor::getEvent()
        );
    }
}
