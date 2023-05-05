<?php

namespace App\Service\Publisher;

use App\Client\Github\GithubAppInstallationClient;
use App\Enum\ProviderEnum;
use App\Exception\PublishException;
use App\Model\PublishableCoverageDataInterface;
use App\Model\Upload;
use Symfony\Component\HttpFoundation\Response;

class GithubPullRequestCommentPublisherService implements PublisherServiceInterface
{
    private const BOT_ID = 'BOT_kgDOB-Qpag';

    public function __construct(
        private readonly GithubAppInstallationClient $client
    ) {
    }

    public function supports(Upload $upload, PublishableCoverageDataInterface $coverageData): bool
    {
        return $upload->getProvider() === ProviderEnum::GITHUB;
    }

    public function publish(Upload $upload, PublishableCoverageDataInterface $coverageData): bool
    {
        if (!$this->supports($upload, $coverageData)) {
            throw PublishException::notSupportedException();
        }

        return $this->upsertComment(
            $upload->getOwner(),
            $upload->getRepository(),
            $upload->getPullRequest(),
            $this->buildCommentBody($upload, $coverageData)
        );
    }

    private function buildCommentBody(Upload $upload, PublishableCoverageDataInterface $coverageData): string
    {
        $body = "### New Coverage Information\n\r";
        $body .= sprintf("This is for %s commit \n\r", $upload->getCommit());

        if ($coverageData->getCoveragePercentage()) {
            $body .= sprintf("Total coverage is: **%s%%**\n\r", $coverageData->getCoveragePercentage());
        }

        if ($coverageData->getCoveragePercentage()) {
            $body .= sprintf(
                "Consisting of *%s* covered lines, out of *%s* total lines.",
                $coverageData->getAtLeastPartiallyCoveredLines(),
                $coverageData->getTotalLines()
            );
        }

        return $body;
    }

    private function upsertComment(
        string $owner,
        string $repository,
        int $pullRequest,
        string $body
    ): bool {
        $this->client->authenticateAsRepositoryOwner($owner);

        $existingComment = $this->getExistingCommentId(
            $owner,
            $repository,
            $pullRequest
        );

        if (!$existingComment) {
            $this->client->api('issue')
                ->comments()
                ->create(
                    $owner,
                    $repository,
                    $pullRequest,
                    [
                        "body" => $body
                    ]
                );

            return $this->client->getLastResponse()->getStatusCode() === Response::HTTP_OK;
        }

        $this->client->api('issue')
            ->comments()
            ->update(
                $owner,
                $repository,
                $existingComment,
                [
                    "body" => $body
                ]
            );

        return $this->client->getLastResponse()->getStatusCode() === Response::HTTP_OK;
    }

    private function getExistingCommentId(string $owner, string $repository, int $pullRequest): ?string
    {
        $allComments = $this->client->api('issue')
            ->comments()
            ->all($owner, $repository, $pullRequest);

        $comments = array_filter(
            $allComments,
            static fn(array $comment) => isset($comment["id"]) &&
                isset($comment['user']['node_id']) &&
                $comment['user']['node_id'] === self::BOT_ID
        );

        if (!empty($comments)) {
            /** @var string $id */
            $id = end($comments)["id"];

            return $id;
        }

        return null;
    }
}
