<?php

declare(strict_types=1);

namespace App\Tests\Event;

use App\Event\ConfigurationFileChangeEventProcessor;
use DateTimeImmutable;
use Github\Api\Repo;
use Github\Api\Repository\Contents;
use Iterator;
use Packages\Clients\Client\Github\GithubAppInstallationClientInterface;
use Packages\Configuration\Mock\MockSettingServiceFactory;
use Packages\Configuration\Service\ConfigurationFileService;
use Packages\Configuration\Service\SettingServiceInterface;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Provider\Provider;
use Packages\Contracts\Tag\Tag;
use Packages\Event\Model\ConfigurationFileChange;
use Packages\Event\Model\EventInterface;
use Packages\Event\Model\IngestFailure;
use Packages\Event\Model\Upload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ConfigurationFileChangeEventProcessorTest extends TestCase
{
    #[DataProvider('eventDataProvider')]
    public function testProcessEvent(
        EventInterface $event,
        bool $isProcessable
    ): void {
        $mockContentsEndpoint = $this->createMock(Contents::class);
        $mockContentsEndpoint->expects($this->exactly((int)$isProcessable))
            ->method('download')
            ->willReturn(
                <<<YAML
                line_annotations: false
                YAML
            );

        $mockRepoApi = $this->createMock(Repo::class);
        $mockRepoApi->expects($this->exactly((int)$isProcessable))
            ->method('contents')
            ->willReturn($mockContentsEndpoint);

        $mockGithubClient = $this->createMock(GithubAppInstallationClientInterface::class);
        $mockGithubClient->expects($this->exactly((int)$isProcessable))
            ->method('repo')
            ->willReturn($mockRepoApi);
        $mockGithubClient->expects($this->exactly((int)$isProcessable))
            ->method('authenticateAsRepositoryOwner')
            ->with($event->getOwner());

        $mockSettingService = $this->createMock(SettingServiceInterface::class);
        $mockSettingService->method('set')
            ->willReturn($isProcessable);
        $mockSettingService->method('delete')
            ->willReturn($isProcessable);

        $configurationFileChangeEventProcessor = new ConfigurationFileChangeEventProcessor(
            new NullLogger(),
            $mockGithubClient,
            new ConfigurationFileService($mockSettingService)
        );

        $this->assertSame(
            $isProcessable,
            $configurationFileChangeEventProcessor->process(
                $event
            )
        );
    }

    public function testProcessingEventNotOnMainRef(): void
    {
        $mockGithubClient = $this->createMock(GithubAppInstallationClientInterface::class);
        $mockGithubClient->expects($this->never())
            ->method('repo');
        $mockGithubClient->expects($this->never())
            ->method('authenticateAsRepositoryOwner');

        $configurationFileChangeEventProcessor = new ConfigurationFileChangeEventProcessor(
            new NullLogger(),
            $mockGithubClient,
            new ConfigurationFileService(MockSettingServiceFactory::createMock([]))
        );

        $this->assertTrue(
            $configurationFileChangeEventProcessor->process(
                new ConfigurationFileChange(
                    provider: Provider::GITHUB,
                    projectId: '0192c0b2-a63e-7c29-8636-beb65b9097ee',
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

    /**
     * @return Iterator<list{ EventInterface, bool }>
     */
    public static function eventDataProvider(): Iterator
    {
        yield [
            new ConfigurationFileChange(
                provider: Provider::GITHUB,
                projectId: '0192c0b2-a63e-7c29-8636-beb65b9097ee',
                owner: 'owner',
                repository: 'repository',
                ref: 'main',
                commit: 'commit',
            ),
            true
        ];

        yield [
            new ConfigurationFileChange(
                provider: Provider::GITHUB,
                projectId: '0192c0b2-a63e-7c29-8636-beb65b9097ee',
                owner: 'owner',
                repository: 'repository',
                ref: 'master',
                commit: 'commit',
            ),
            true
        ];

        yield [
            new IngestFailure(
                new Upload(
                    uploadId: 'uploadId',
                    provider: Provider::GITHUB,
                    projectId: '0192c0b2-a63e-7c29-8636-beb65b9097ee',
                    owner: 'owner',
                    repository: 'repository',
                    commit: 'commit',
                    parent: ['parent'],
                    ref: 'ref',
                    projectRoot: 'projectRoot',
                    tag: new Tag('tag', 'value', [2]),
                    eventTime: new DateTimeImmutable()
                ),
                new DateTimeImmutable()
            ),
            false
        ];
    }
}
