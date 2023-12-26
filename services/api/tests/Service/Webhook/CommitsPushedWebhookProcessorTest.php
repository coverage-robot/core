<?php

namespace App\Tests\Service\Webhook;

use App\Client\EventBridgeEventClient;
use App\Entity\Project;
use App\Model\Webhook\Github\GithubPushedCommit;
use App\Model\Webhook\Github\GithubPushWebhook;
use App\Service\Webhook\CommitsPushedWebhookProcessor;
use DateTimeImmutable;
use Packages\Configuration\Constant\ConfigurationFile;
use Packages\Event\Model\ConfigurationFileChange;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class CommitsPushedWebhookProcessorTest extends TestCase
{
    #[DataProvider('pushedCommitsDataProvider')]
    public function testHandlingWebhooksWithDifferentCommits(
        GithubPushWebhook $webhook,
        bool $shouldSendConfigurationFileChangeEvent
    ): void {
        $mockEventBridgeClient = $this->createMock(EventBridgeEventClient::class);
        $mockEventBridgeClient->expects($this->exactly((int)$shouldSendConfigurationFileChangeEvent))
            ->method('publishEvent')
            ->with($this->isInstanceOf(ConfigurationFileChange::class));

        $processor = new CommitsPushedWebhookProcessor(
            new NullLogger(),
            $mockEventBridgeClient
        );

        $processor->process(
            $this->createMock(Project::class),
            $webhook
        );
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
