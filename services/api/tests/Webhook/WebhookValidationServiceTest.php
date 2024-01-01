<?php

namespace App\Tests\Webhook;

use App\Exception\InvalidWebhookException;
use App\Model\Webhook\Github\GithubPushedCommit;
use App\Model\Webhook\Github\GithubPushWebhook;
use App\Model\Webhook\WebhookInterface;
use App\Service\WebhookValidationService;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

class WebhookValidationServiceTest extends TestCase
{
    #[DataProvider('webhookDataProvider')]
    public function testValidatingMessages(WebhookInterface $webhook, bool $isValid): void
    {
        $webhookValidatorService = new WebhookValidationService(
            Validation::createValidatorBuilder()
                ->enableAttributeMapping()
                ->getValidator()
        );

        if (!$isValid) {
            $this->expectException(InvalidWebhookException::class);
        }

        $valid = $webhookValidatorService->validate($webhook);

        if ($isValid) {
            $this->assertTrue($valid);
        }
    }

    public static function webhookDataProvider(): array
    {
        return [
            [
                new GithubPushWebhook(
                    signature: 'mock-signature',
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    headCommit: 'mock-commit-1',
                    commits: [
                        new GithubPushedCommit(
                            commit: 'mock-commit-1',
                            addedFiles: [],
                            modifiedFiles: [],
                            deletedFiles: [],
                            committedAt: new DateTimeImmutable()
                        )
                    ]
                ),
                true
            ],
            [
                new GithubPushWebhook(
                    signature: 'mock-signature',
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    headCommit: 'mock-commit-2',
                    commits: [
                        new GithubPushedCommit(
                            commit: 'mock-commit-1',
                            addedFiles: [],
                            modifiedFiles: [],
                            deletedFiles: [],
                            committedAt: new DateTimeImmutable()
                        ),
                        new GithubPushedCommit(
                            commit: 'mock-commit-2',
                            addedFiles: [],
                            modifiedFiles: [],
                            deletedFiles: [],
                            committedAt: new DateTimeImmutable()
                        )
                    ]
                ),
                true
            ],
            [
                new GithubPushWebhook(
                    signature: 'mock-signature',
                    owner: '',
                    repository: 'mock-repository',
                    ref: '',
                    headCommit: 'mock-commit-2',
                    commits: [
                        new GithubPushedCommit(
                            commit: 'mock-commit-2',
                            addedFiles: [],
                            modifiedFiles: [],
                            deletedFiles: [],
                            committedAt: new DateTimeImmutable()
                        ),
                    ]
                ),
                false
            ],
            [
                new GithubPushWebhook(
                    signature: 'mock-signature',
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    headCommit: 'mock-commit-2',
                    commits: []
                ),
                false
            ],
            [
                new GithubPushWebhook(
                    signature: 'mock-signature',
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    headCommit: '',
                    commits: [
                        new GithubPushedCommit(
                            commit: 'mock-commit-1',
                            addedFiles: [],
                            modifiedFiles: [],
                            deletedFiles: [],
                            committedAt: new DateTimeImmutable()
                        ),
                        new GithubPushedCommit(
                            commit: 'mock-commit-2',
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
