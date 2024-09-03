<?php

namespace App\Service\Publisher\Github;

use App\Enum\EnvironmentVariable;
use App\Enum\TemplateVariant;
use App\Exception\PublishingNotSupportedException;
use App\Service\Publisher\PublisherServiceInterface;
use App\Service\Templating\TemplateRenderingService;
use Github\Exception\ExceptionInterface;
use Override;
use Packages\Clients\Client\Github\GithubAppInstallationClientInterface;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Contracts\Event\EventInterface;
use Packages\Contracts\Provider\Provider;
use Packages\Contracts\PublishableMessage\PublishableMessageInterface;
use Packages\Message\PublishableMessage\PublishablePullRequestMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

final class GithubPullRequestCommentPublisherService implements PublisherServiceInterface
{
    public function __construct(
        private readonly GithubAppInstallationClientInterface $client,
        private readonly TemplateRenderingService $templateRenderingService,
        private readonly EnvironmentServiceInterface $environmentService,
        private readonly LoggerInterface $pullRequestPublisherLogger
    ) {
    }

    #[Override]
    public function supports(PublishableMessageInterface $publishableMessage): bool
    {
        if (!$publishableMessage instanceof PublishablePullRequestMessage) {
            return false;
        }

        if ($publishableMessage->getEvent()->getPullRequest() === null) {
            return false;
        }

        return $publishableMessage->getEvent()->getProvider() === Provider::GITHUB;
    }

    /**
     * Publish a PR comment with segmented breakdowns extracted from the uploaded coverage data.
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

        /** @var PublishablePullRequestMessage $publishableMessage */
        $pullRequest = (int)$publishableMessage->getEvent()->getPullRequest();

        /** @var EventInterface $event */
        $event = $publishableMessage->getEvent();

        try {
            return $this->upsertComment(
                $event->getOwner(),
                $event->getRepository(),
                $pullRequest,
                $this->templateRenderingService->render(
                    $publishableMessage,
                    TemplateVariant::FULL_PULL_REQUEST_COMMENT
                )
            );
        } catch (ExceptionInterface $exception) {
            $this->pullRequestPublisherLogger->error(
                sprintf(
                    'Failed to publish pull request comment for %s',
                    (string)$event
                ),
                [
                    'exception' => $exception
                ]
            );

            return false;
        }
    }

    #[Override]
    public static function getPriority(): int
    {
        return 0;
    }

    /**
     * @throws ExceptionInterface
     */
    private function upsertComment(
        string $owner,
        string $repository,
        int $pullRequest,
        string $body
    ): bool {
        $this->client->authenticateAsRepositoryOwner($owner);

        $api = $this->client->issue();

        $existingComment = $this->getExistingCommentId(
            $owner,
            $repository,
            $pullRequest
        );

        if ($existingComment === null) {
            $api->comments()
                ->create(
                    $owner,
                    $repository,
                    $pullRequest,
                    [
                        'body' => $body
                    ]
                );

            if ($this->client->getLastResponse()?->getStatusCode() !== Response::HTTP_CREATED) {
                $this->pullRequestPublisherLogger->critical(
                    sprintf(
                        '%s status code returned while attempting to create a new pull request comment for results.',
                        (string)$this->client->getLastResponse()?->getStatusCode()
                    )
                );

                return false;
            }

            return true;
        }

        $api->comments()
            ->update(
                $owner,
                $repository,
                $existingComment,
                [
                    'body' => $body
                ]
            );

        if ($this->client->getLastResponse()?->getStatusCode() !== Response::HTTP_OK) {
            $this->pullRequestPublisherLogger->critical(
                sprintf(
                    '%s status code returned while updating pull request comment with new results.',
                    (string)$this->client->getLastResponse()?->getStatusCode()
                )
            );

            return false;
        }

        return true;
    }

    private function getExistingCommentId(string $owner, string $repository, int $pullRequest): ?int
    {
        $api = $this->client->issue();
        $appId = $this->environmentService->getVariable(EnvironmentVariable::GITHUB_APP_ID);

        /** @var array{ id: int, performed_via_github_app?: array{ id: int } }[] $comments */
        $comments = array_filter(
            $api->comments()->all($owner, $repository, $pullRequest),
            static fn(array $comment): bool => isset($comment['id'], $comment['performed_via_github_app']['id']) &&
                (string)$comment['performed_via_github_app']['id'] === $appId
        );

        if (!empty($comments)) {
            return end($comments)['id'];
        }

        return null;
    }
}
