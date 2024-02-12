<?php

namespace App\Service\Publisher\Github;

use App\Enum\EnvironmentVariable;
use App\Enum\TemplateVariant;
use App\Exception\PublishException;
use App\Service\Publisher\PublisherServiceInterface;
use App\Service\Templating\TemplateRenderingService;
use Packages\Clients\Client\Github\GithubAppInstallationClient;
use Packages\Clients\Client\Github\GithubAppInstallationClientInterface;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Contracts\Provider\Provider;
use Packages\Contracts\PublishableMessage\PublishableMessageInterface;
use Packages\Message\PublishableMessage\PublishablePullRequestMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;

final class GithubPullRequestCommentPublisherService implements PublisherServiceInterface
{
    public function __construct(
        #[Autowire(service: GithubAppInstallationClient::class)]
        private readonly GithubAppInstallationClientInterface $client,
        private readonly TemplateRenderingService $templateRenderingService,
        private readonly EnvironmentServiceInterface $environmentService,
        private readonly LoggerInterface $pullRequestPublisherLogger
    ) {
    }

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
    public function publish(PublishableMessageInterface $publishableMessage): bool
    {
        if (!$this->supports($publishableMessage)) {
            throw PublishException::notSupportedException();
        }

        /** @var PublishablePullRequestMessage $publishableMessage */
        $pullRequest = (int)$publishableMessage->getEvent()->getPullRequest();

        $event = $publishableMessage->getEvent();

        return $this->upsertComment(
            $event->getOwner(),
            $event->getRepository(),
            $pullRequest,
            $this->templateRenderingService->render(
                $publishableMessage,
                TemplateVariant::COMPLETE
            )
        );
    }

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
        $botId = $this->environmentService->getVariable(EnvironmentVariable::GITHUB_BOT_ID);

        /** @var array{ id: int, user: array{ node_id: string } }[] $comments */
        $comments = array_filter(
            $api->comments()->all($owner, $repository, $pullRequest),
            static fn(array $comment): bool => isset($comment['id'], $comment['user']['node_id']) &&
                $comment['user']['node_id'] === $botId
        );

        if (!empty($comments)) {
            return end($comments)['id'];
        }

        return null;
    }
}
