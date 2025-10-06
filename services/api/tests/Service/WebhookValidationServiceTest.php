<?php

declare(strict_types=1);

namespace App\Tests\Webhook;

use App\Exception\InvalidWebhookException;
use App\Model\Webhook\Github\GithubPushedCommit;
use App\Model\Webhook\Github\GithubPushWebhook;
use App\Model\Webhook\WebhookInterface;
use App\Service\WebhookValidationService;
use DateTimeImmutable;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class WebhookValidationServiceTest extends TestCase
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

    /**
     * @return Iterator<list{ GithubPushWebhook, bool }>
     */
    public static function webhookDataProvider(): Iterator
    {
        yield [
            new GithubPushWebhook(
                signature: 'mock-signature',
                owner: 'mock-owner',
                repository: 'mock-repository',
                ref: 'mock-ref',
                headCommit: 'cbf2bc5ab608a72a1577379f1d51d28cc494ccf1',
                commits: [
                    new GithubPushedCommit(
                        commit: 'cbf2bc5ab608a72a1577379f1d51d28cc494ccf1',
                        addedFiles: [],
                        modifiedFiles: [],
                        deletedFiles: [],
                        committedAt: new DateTimeImmutable()
                    )
                ]
            ),
            true
        ];

        yield [
            new GithubPushWebhook(
                signature: 'mock-signature',
                owner: 'mock-owner',
                repository: 'mock-repository',
                ref: 'mock-ref',
                headCommit: 'abf2bc5ab608a72a1577379f1d51d28cc494ccf1',
                commits: [
                    new GithubPushedCommit(
                        commit: 'cbf2bc5ab608a72a1577379f1d51d28cc494ccf1',
                        addedFiles: [],
                        modifiedFiles: [],
                        deletedFiles: [],
                        committedAt: new DateTimeImmutable()
                    ),
                    new GithubPushedCommit(
                        commit: 'abf2bc5ab608a72a1577379f1d51d28cc494ccf1',
                        addedFiles: [],
                        modifiedFiles: [],
                        deletedFiles: [],
                        committedAt: new DateTimeImmutable()
                    )
                ]
            ),
            true
        ];

        yield [
            new GithubPushWebhook(
                signature: 'mock-signature',
                owner: '',
                repository: 'mock-repository',
                ref: '',
                headCommit: 'abf2bc5ab608a72a1577379f1d51d28cc494ccf1',
                commits: [
                    new GithubPushedCommit(
                        commit: 'abf2bc5ab608a72a1577379f1d51d28cc494ccf1',
                        addedFiles: [],
                        modifiedFiles: [],
                        deletedFiles: [],
                        committedAt: new DateTimeImmutable()
                    ),
                ]
            ),
            false
        ];

        yield [
            new GithubPushWebhook(
                signature: 'mock-signature',
                owner: 'mock-owner',
                repository: 'mock-repository',
                ref: 'mock-ref',
                headCommit: 'abf2bc5ab608a72a1577379f1d51d28cc494ccf1',
                commits: []
            ),
            // Webhooks for when refs are deleted (i.e. PRs merged)
            // wont have any commits associated with them
            true
        ];

        yield [
            new GithubPushWebhook(
                signature: 'mock-signature',
                owner: 'mock-owner',
                repository: 'mock-repository',
                ref: 'mock-ref',
                headCommit: '',
                commits: [
                    new GithubPushedCommit(
                        commit: 'cbf2bc5ab608a72a1577379f1d51d28cc494ccf1',
                        addedFiles: [],
                        modifiedFiles: [],
                        deletedFiles: [],
                        committedAt: new DateTimeImmutable()
                    ),
                    new GithubPushedCommit(
                        commit: 'abf2bc5ab608a72a1577379f1d51d28cc494ccf1',
                        addedFiles: [],
                        modifiedFiles: [],
                        deletedFiles: [],
                        committedAt: new DateTimeImmutable()
                    )
                ]
            ),
            false
        ];

        yield [
            new GithubPushWebhook(
                signature: 'mock-signature',
                owner: 'mock-owner',
                repository: 'mock-repository',
                ref: 'mock-ref',
                headCommit: 'cbf2bc5ab608a72a1577379f1d51d28cc494ccf1',
                commits: [
                    new GithubPushedCommit(
                        commit: 'cbf2bc5ab608a72a1577379f1d51d28cc494ccf1',
                        addedFiles: [],
                        modifiedFiles: [],
                        deletedFiles: [],
                        // Notice that the commit was made **in the future**, this simulates
                        // a occasional webhooks GitHub might send, where the timestamp is ahead
                        // of the time we receive the webhook.
                        committedAt: new DateTimeImmutable('+3 seconds')
                    )
                ]
            ),
            true
        ];
    }
}
