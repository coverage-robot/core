<?php

namespace App\Tests\Event;

use App\Event\ConfigurationFileChangeEventProcessor;
use DateTimeImmutable;
use Github\Api\Repo;
use Github\Api\Repository\Contents;
use Packages\Clients\Client\Github\GithubAppInstallationClient;
use Packages\Configuration\Service\ConfigurationFileService;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Model\ConfigurationFileChange;
use Packages\Event\Model\EventInterface;
use Packages\Event\Model\IngestFailure;
use Packages\Event\Model\Upload;
use Packages\Contracts\Tag\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ConfigurationFileChangeEventProcessorTest extends TestCase
{
    #[DataProvider('eventDataProvider')]
    public function testProcessEvent(
        EventInterface $event,
        bool $isProcessable
    ): void {
        $mockContentsEndpoint = $this->createMock(Contents::class);
        $mockContentsEndpoint->expects($this->exactly((int)$isProcessable))
            ->method('download')
            ->willReturn('mock-file');

        $mockRepoApi = $this->createMock(Repo::class);
        $mockRepoApi->expects($this->exactly((int)$isProcessable))
            ->method('contents')
            ->willReturn($mockContentsEndpoint);

        $mockGithubClient = $this->createMock(GithubAppInstallationClient::class);
        $mockGithubClient->expects($this->exactly((int)$isProcessable))
            ->method('repo')
            ->willReturn($mockRepoApi);
        $mockGithubClient->expects($this->exactly((int)$isProcessable))
            ->method('authenticateAsRepositoryOwner')
            ->with($event->getOwner());

        $mockConfigurationFileService = $this->createMock(ConfigurationFileService::class);
        $mockConfigurationFileService->expects($this->exactly((int)$isProcessable))
            ->method('parseAndPersistFile')
            ->with(
                $event->getProvider(),
                $event->getOwner(),
                $event->getRepository(),
                'mock-file'
            )
            ->willReturn(true);

        $configurationFileChangeEventProcessor = new ConfigurationFileChangeEventProcessor(
            new NullLogger(),
            $mockGithubClient,
            $mockConfigurationFileService
        );

        $this->assertEquals(
            $isProcessable,
            $configurationFileChangeEventProcessor->process(
                $event
            )
        );
    }

    public function testProcessingEventNotOnMainRef(): void
    {
        $mockGithubClient = $this->createMock(GithubAppInstallationClient::class);
        $mockGithubClient->expects($this->never())
            ->method('repo');
        $mockGithubClient->expects($this->never())
            ->method('authenticateAsRepositoryOwner');

        $mockConfigurationFileService = $this->createMock(ConfigurationFileService::class);
        $mockConfigurationFileService->expects($this->never())
            ->method('parseAndPersistFile');

        $configurationFileChangeEventProcessor = new ConfigurationFileChangeEventProcessor(
            new NullLogger(),
            $mockGithubClient,
            $mockConfigurationFileService
        );

        $this->assertTrue(
            $configurationFileChangeEventProcessor->process(
                new ConfigurationFileChange(
                    provider: Provider::GITHUB,
                    owner: 'owner',
                    repository: 'repository',
                    ref: 'not-main-ref',
                    commit: 'commit',
                )
            )
        );
    }

    public function testGetEvent(): void
    {
        $this->assertEquals(
            Event::CONFIGURATION_FILE_CHANGE->value,
            ConfigurationFileChangeEventProcessor::getEvent()
        );
    }

    public static function eventDataProvider(): array
    {
        return [
            [
                new ConfigurationFileChange(
                    provider: Provider::GITHUB,
                    owner: 'owner',
                    repository: 'repository',
                    ref: 'main',
                    commit: 'commit',
                ),
                true
            ],
            [
                new ConfigurationFileChange(
                    provider: Provider::GITHUB,
                    owner: 'owner',
                    repository: 'repository',
                    ref: 'master',
                    commit: 'commit',
                ),
                true
            ],
            [
                new IngestFailure(
                    new Upload(
                        uploadId: 'uploadId',
                        provider: Provider::GITHUB,
                        owner: 'owner',
                        repository: 'repository',
                        commit: 'commit',
                        parent: ['parent'],
                        ref: 'ref',
                        projectRoot: 'projectRoot',
                        tag: new Tag('tag', 'value'),
                        eventTime: new DateTimeImmutable()
                    ),
                    new DateTimeImmutable()
                ),
                false
            ]
        ];
    }
}
