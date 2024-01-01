<?php

namespace App\Webhook;

use App\Entity\Project;
use App\Enum\WebhookProcessorEvent;
use App\Model\Webhook\CommitsPushedWebhookInterface;
use App\Model\Webhook\PushedCommitInterface;
use App\Model\Webhook\WebhookInterface;
use AsyncAws\Core\Exception\Http\HttpException;
use JsonException;
use Packages\Configuration\Constant\ConfigurationFile;
use Packages\Contracts\Event\EventSource;
use Packages\Event\Client\EventBusClient;
use Packages\Event\Model\ConfigurationFileChange;
use Psr\Log\LoggerInterface;
use RuntimeException;

class CommitsPushedWebhookProcessor implements WebhookProcessorInterface
{
    public function __construct(
        private readonly LoggerInterface $webhookProcessorLogger,
        private readonly EventBusClient $eventBusClient
    ) {
    }

    /**
     * Process any webhooks received from third-party providers which relate to pushes of commits.
     *
     * This is so we can track changes to the version controlled configuration file, and distribute
     * it amongst the services.
     */
    public function process(Project $project, WebhookInterface $webhook): void
    {
        if (!$webhook instanceof CommitsPushedWebhookInterface) {
            throw new RuntimeException(
                sprintf(
                    'Webhook is not an instance of %s',
                    CommitsPushedWebhookInterface::class
                )
            );
        }

        $this->webhookProcessorLogger->info(
            sprintf(
                'Processing commits pushed for %s',
                (string)$webhook,
            )
        );

        if (!str_starts_with($webhook->getRef(), 'refs/heads/')) {
            $this->webhookProcessorLogger->info(
                sprintf(
                    'Ignoring %s as its not a push to a branch',
                    (string)$webhook,
                ),
                [
                    'ref' => $webhook->getRef(),
                ]
            );
            return;
        }

        // Remove the prefix to get just the ref name, which we can then
        // pass around in events.
        $ref = substr($webhook->getRef(), strlen('refs/heads/'));

        /** @var string[] $effectedFiles */
        $effectedFiles = array_reduce(
            $webhook->getCommits(),
            static fn (array $files, PushedCommitInterface $commit) => [
                ...$files,
                ...$commit->getAddedFiles(),
                ...$commit->getModifiedFiles(),
                ...$commit->getDeletedFiles(),
            ],
            [],
        );

        if ($this->isConfigurationFileEffected($effectedFiles)) {
            $this->webhookProcessorLogger->info(
                sprintf(
                    'Configuration file has been effected by commits in %s',
                    (string)$webhook,
                ),
                [
                    'effectedFiles' => $effectedFiles,
                ]
            );

            $headCommit = array_filter(
                $webhook->getCommits(),
                static fn (PushedCommitInterface $commit) =>
                    $commit->getCommit() === $webhook->getHeadCommit()
            );

            if (count($headCommit) !== 1) {
                throw new RuntimeException(
                    sprintf(
                        'Expected to find 1 head commit, found %d for %s',
                        count($headCommit),
                        (string)$webhook
                    )
                );
            }

            $this->publishEvent(
                new ConfigurationFileChange(
                    provider: $webhook->getProvider(),
                    owner: $webhook->getOwner(),
                    repository: $webhook->getRepository(),
                    ref: $ref,
                    commit: $webhook->getHeadCommit(),
                    eventTime: end($headCommit)->getCommittedAt(),
                )
            );
        }
    }

    /**
     * @param string[] $effectedFiles
     */
    private function isConfigurationFileEffected(array $effectedFiles): bool
    {
        return in_array(
            ConfigurationFile::PATH,
            $effectedFiles,
            true
        );
    }

    private function publishEvent(ConfigurationFileChange $configurationFileChange): void
    {
        try {
            $this->eventBusClient->fireEvent(
                EventSource::API,
                $configurationFileChange
            );
        } catch (HttpException | JsonException $e) {
            $this->webhookProcessorLogger->error(
                sprintf(
                    'Failed to publish configuration file change event: %s',
                    (string)$configurationFileChange
                ),
                [
                    'exception' => $e
                ]
            );
        }
    }

    public static function getEvent(): string
    {
        return WebhookProcessorEvent::COMMITS_PUSHED->value;
    }
}
