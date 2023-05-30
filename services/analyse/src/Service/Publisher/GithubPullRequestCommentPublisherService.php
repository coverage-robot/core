<?php

namespace App\Service\Publisher;

use App\Client\Github\GithubAppClient;
use App\Client\Github\GithubAppInstallationClient;
use App\Enum\ProviderEnum;
use App\Exception\PublishException;
use App\Model\PublishableCoverageDataInterface;
use App\Model\Upload;
use App\Service\Formatter\PullRequestCommentFormatterService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

class GithubPullRequestCommentPublisherService implements PublisherServiceInterface
{
    public function __construct(
        private readonly GithubAppInstallationClient        $client,
        private readonly PullRequestCommentFormatterService $pullRequestCommentFormatter,
        private readonly LoggerInterface                    $pullRequestPublisherLogger
    ) {
    }

    public function supports(Upload $upload, PublishableCoverageDataInterface $coverageData): bool
    {
        if (!$upload->getPullRequest()) {
            return false;
        }

        return $upload->getProvider() === ProviderEnum::GITHUB;
    }

    public function publish(Upload $upload, PublishableCoverageDataInterface $coverageData): bool
    {
        if (!$this->supports($upload, $coverageData)) {
            throw PublishException::notSupportedException();
        }

        /** @var int $pullRequest */
        $pullRequest = $upload->getPullRequest();

        return $this->upsertComment(
            $upload->getOwner(),
            $upload->getRepository(),
            $pullRequest,
            $this->pullRequestCommentFormatter->format($upload, $coverageData)
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

        if (!$existingComment) {
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

        /** @var array{ id: int, user: array{ node_id: string } }[] $comments */
        $comments = array_filter(
            $api->comments()->all($owner, $repository, $pullRequest),
            static fn(array $comment) => isset($comment['id']) &&
                isset($comment['user']['node_id']) &&
                $comment['user']['node_id'] === GithubAppClient::BOT_ID
        );

        if (!empty($comments)) {
            return end($comments)['id'];
        }

        return null;
    }

    public static function getPriority(): int
    {
        return 0;
    }
}
