<?php

declare(strict_types=1);

namespace App\Tests\Webhook\Processor;

use App\Client\CognitoClientInterface;
use App\Model\Project;
use App\Model\Webhook\Github\GithubPushedCommit;
use App\Model\Webhook\Github\GithubPushWebhook;
use App\Webhook\Processor\CommitsPushedWebhookProcessor;
use DateTimeImmutable;
use Packages\Configuration\Constant\ConfigurationFile;
use Packages\Contracts\Event\EventSource;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Client\EventBusClientInterface;
use Packages\Event\Model\ConfigurationFileChange;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class CommitsPushedWebhookProcessorTest extends TestCase
{
    #[DataProvider('pushedCommitsDataProvider')]
    public function testHandlingWebhooksWithDifferentCommits(
        GithubPushWebhook $webhook,
        bool $shouldSendConfigurationFileChangeEvent
    ): void {
        $mockEventBusClient = $this->createMock(EventBusClientInterface::class);
        $mockEventBusClient->expects($this->exactly((int)$shouldSendConfigurationFileChangeEvent))
            ->method('fireEvent')
            ->with($this->isInstanceOf(ConfigurationFileChange::class));

        $mockCognitoClient = $this->createMock(CognitoClientInterface::class);
        $mockCognitoClient->expects($this->exactly((int)$shouldSendConfigurationFileChangeEvent))
            ->method('getProject')
            ->willReturn(new Project(
                provider: Provider::GITHUB,
                projectId: 'mock-project-id',
                owner: 'mock-owner',
                repository: 'mock-repository',
                email: 'mock-email',
                graphToken: 'mock-graph-token',
            ));

        $processor = new CommitsPushedWebhookProcessor(
            new NullLogger(),
            $mockEventBusClient,
            $mockCognitoClient
        );

        $processor->process($webhook);
    }

    public static function pushedCommitsDataProvider(): array
    {
        return [
            'No configuration file push (single commit)' => [
                new GithubPushWebhook(
                    signature: 'mock-signature',
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'refs/heads/master',
                    headCommit: 'mock-head-commit',
                    commits: [
                        new GithubPushedCommit(
                            commit: 'mock-commit-id',
                            addedFiles: [],
                            modifiedFiles: [],
                            deletedFiles: [],
                            committedAt: new DateTimeImmutable()
                        )
                    ]
                ),
                false
            ],
            'No configuration file push (multiple commits)' => [
                new GithubPushWebhook(
                    signature: 'mock-signature',
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'refs/heads/master',
                    headCommit: 'mock-head-commit',
                    commits: [
                        new GithubPushedCommit(
                            commit: 'mock-commit-1',
                            addedFiles: [
                                'some-file.yml'
                            ],
                            modifiedFiles: [],
                            deletedFiles: [
                                'a-different-file.txt'
                            ],
                            committedAt: new DateTimeImmutable()
                        ),
                        new GithubPushedCommit(
                            commit: 'mock-commit-2',
                            addedFiles: [],
                            modifiedFiles: ['another-file.php'],
                            deletedFiles: [],
                            committedAt: new DateTimeImmutable()
                        )
                    ]
                ),
                false
            ],
            'ConfigurationFile file push (single commits)' => [
                new GithubPushWebhook(
                    signature: 'mock-signature',
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'refs/heads/master',
                    headCommit: 'mock-head-commit',
                    commits: [
                        new GithubPushedCommit(
                            commit: 'mock-commit-1',
                            addedFiles: [
                                ConfigurationFile::PATH
                            ],
                            modifiedFiles: [],
                            deletedFiles: [
                                'a-different-file.txt'
                            ],
                            committedAt: new DateTimeImmutable()
                        ),
                        new GithubPushedCommit(
                            commit: 'mock-head-commit',
                            addedFiles: [],
                            modifiedFiles: ['another-file.php'],
                            deletedFiles: [],
                            committedAt: new DateTimeImmutable()
                        )
                    ]
                ),
                true
            ],
            'ConfigurationFile file push (multiple commits)' => [
                new GithubPushWebhook(
                    signature: 'mock-signature',
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'refs/heads/master',
                    headCommit: 'mock-head-commit',
                    commits: [
                        new GithubPushedCommit(
                            commit: 'mock-commit-1',
                            addedFiles: [],
                            modifiedFiles: [
                                ConfigurationFile::PATH
                            ],
                            deletedFiles: [],
                            committedAt: new DateTimeImmutable()
                        ),
                        new GithubPushedCommit(
                            commit: 'mock-head-commit',
                            addedFiles: [],
                            modifiedFiles: [],
                            deletedFiles: [
                                ConfigurationFile::PATH
                            ],
                            committedAt: new DateTimeImmutable()
                        )
                    ]
                ),
                true
            ],
            'ConfigurationFile file push (in tag)' => [
                new GithubPushWebhook(
                    signature: 'mock-signature',
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'refs/tag/v1.0',
                    headCommit: 'mock-head-commit',
                    commits: [
                        new GithubPushedCommit(
                            commit: 'mock-commit-id',
                            addedFiles: [],
                            modifiedFiles: [],
                            deletedFiles: [],
                            committedAt: new DateTimeImmutable()
                        )
                    ]
                ),
                false
            ],
        ];
    }
}
