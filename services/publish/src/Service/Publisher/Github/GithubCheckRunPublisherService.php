<?php

declare(strict_types=1);

namespace App\Service\Publisher\Github;

use App\Exception\CheckRunNotFoundException;
use App\Exception\PublishingNotSupportedException;
use App\Service\Publisher\PublisherServiceInterface;
use App\Service\Templating\TemplateRenderingService;
use Github\Exception\ExceptionInterface;
use Override;
use Packages\Clients\Client\Github\GithubAppInstallationClientInterface;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Contracts\Provider\Provider;
use Packages\Contracts\PublishableMessage\PublishableMessageInterface;
use Packages\Message\PublishableMessage\PublishableCheckRunMessageInterface;
use Psr\Log\LoggerInterface;

final class GithubCheckRunPublisherService implements PublisherServiceInterface
{
    use GithubCheckRunAwareTrait;

    public function __construct(
        private readonly TemplateRenderingService $templateRenderingService,
        private readonly GithubAppInstallationClientInterface $client,
        private readonly EnvironmentServiceInterface $environmentService,
        private readonly LoggerInterface $checkPublisherLogger
    ) {
    }

    #[Override]
    public function supports(PublishableMessageInterface $publishableMessage): bool
    {
        if (!$publishableMessage instanceof PublishableCheckRunMessageInterface) {
            return false;
        }

        return $publishableMessage->getEvent()->getProvider() === Provider::GITHUB;
    }

    /**
     * Publish a check run to the PR, or commit, with the total coverage percentage.
     */
    #[Override]
    public function publish(PublishableMessageInterface $publishableMessage): bool
    {
        if (!$this->supports($publishableMessage)) {
            throw new PublishingNotSupportedException(
                self::class,
                $publishableMessage
            );
        }

        /** @var PublishableCheckRunMessageInterface $publishableMessage */
        $event = $publishableMessage->getEvent();

        $successful = $this->upsertCheckRun(
            $event->getOwner(),
            $event->getRepository(),
            $event->getCommit(),
            $publishableMessage
        );

        if (!$successful) {
            $this->checkPublisherLogger->critical(
                sprintf(
                    'Failed to publish check run for %s',
                    (string)$event
                )
            );
        }

        return $successful;
    }

    #[Override]
    public static function getPriority(): int
    {
        return 0;
    }

    /**
     * Update an existing check run for the given commit, or create it if it doesnt exist.
     */
    private function upsertCheckRun(
        string $owner,
        string $repository,
        string $commit,
        PublishableCheckRunMessageInterface $publishableMessage
    ): bool {
        $this->client->authenticateAsRepositoryOwner($owner);

        try {
            try {
                $checkRun = $this->getCheckRun(
                    $owner,
                    $repository,
                    $commit
                );

                return $this->updateCheckRun(
                    $owner,
                    $repository,
                    $checkRun['id'],
                    $publishableMessage
                );
            } catch (CheckRunNotFoundException) {
                return $this->createCheckRun(
                    $owner,
                    $repository,
                    $commit,
                    $publishableMessage
                );
            }
        } catch (ExceptionInterface $exception) {
            $this->checkPublisherLogger->critical(
                sprintf(
                    'Exception while writing check run for %s',
                    (string)$publishableMessage->getEvent()
                ),
                [
                    'exception' => $exception
                ]
            );

            return false;
        }
    }
}
