<?php

namespace App\Tests\Service\Event;

use App\Client\EventBridgeEventClient;
use App\Client\SqsMessageClient;
use App\Enum\EnvironmentVariable;
use App\Model\PublishableCoverageDataInterface;
use App\Service\CoverageAnalyserService;
use App\Service\Event\JobStateChangeEventProcessor;
use App\Tests\Mock\Factory\MockEnvironmentServiceFactory;
use Bref\Event\EventBridge\EventBridgeEvent;
use Packages\Clients\Client\Github\GithubAppInstallationClient;
use Packages\Models\Enum\Environment;
use Packages\Models\Enum\EventBus\CoverageEvent;
use Packages\Models\Enum\JobState;
use Packages\Models\Enum\Provider;
use Packages\Models\Enum\PublishableCheckRunStatus;
use Packages\Models\Model\PublishableMessage\PublishableCheckRunMessage;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

class JobStateChangeEventProcessorTest extends KernelTestCase
{
    public function testProcessFirstJob(): void
    {
        $jobStateChange = [
            'provider' => Provider::GITHUB->value,
            'owner' => 'mock-owner',
            'repository' => 'mock-repository',
            'ref' => 'mock-ref',
            'commit' => 'mock-commit',
            'pullRequest' => 'mock-pull-request',
            'externalId' => 'mock-id',
            'index' => 0,
            'state' => JobState::COMPLETED->value,
            'initialState' => true,
            'eventTime' => '2021-01-01T00:00:00+00:00',
        ];

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

        $jobStateChangeEventProcessor->process(
            new EventBridgeEvent([
                'detail-type' => CoverageEvent::JOB_STATE_CHANGE->value,
                'detail' => $jobStateChange
            ])
        );
    }

    public function testProcessorEvent(): void
    {
        $this->assertEquals(
            CoverageEvent::JOB_STATE_CHANGE->value,
            JobStateChangeEventProcessor::getProcessorEvent()
        );
    }
}
