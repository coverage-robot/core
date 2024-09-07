<?php

namespace App\Service\Publisher\Github;

use App\Enum\TemplateVariant;
use App\Exception\PublishingNotSupportedException;
use App\Service\Publisher\PublisherServiceInterface;
use App\Service\Templating\TemplateRenderingService;
use Github\Exception\ExceptionInterface;
use Override;
use Packages\Clients\Client\Github\GithubAppInstallationClientInterface;
use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Model\LineCommentType;
use Packages\Configuration\Service\SettingServiceInterface;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Contracts\Event\EventInterface;
use Packages\Contracts\Provider\Provider;
use Packages\Contracts\PublishableMessage\PublishableMessageInterface;
use Packages\Message\PublishableMessage\PublishableLineCommentInterface;
use Packages\Message\PublishableMessage\PublishableLineCommentMessageCollection;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

final class GithubReviewPublisherService implements PublisherServiceInterface
{
    use GithubPullRequestAwareTrait;

    public function __construct(
        private readonly TemplateRenderingService $templateRenderingService,
        private readonly SettingServiceInterface $settingService,
        private readonly GithubAppInstallationClientInterface $client,
        private readonly EnvironmentServiceInterface $environmentService,
        private readonly LoggerInterface $reviewPublisherLogger
    ) {
    }

    #[Override]
    public function supports(PublishableMessageInterface $publishableMessage): bool
    {
        if (!$publishableMessage instanceof PublishableLineCommentMessageCollection) {
            return false;
        }

        $event = $publishableMessage->getEvent();

        if ($event->getProvider() !== Provider::GITHUB) {
            return false;
        }

        if ($publishableMessage->getEvent()->getPullRequest() === null) {
            return false;
        }

        /** @var LineCommentType $lineCommentType */
        $lineCommentType = $this->settingService->get(
            $event->getProvider(),
            $publishableMessage->getEvent()
                ->getOwner(),
            $publishableMessage->getEvent()
                ->getRepository(),
            SettingKey::LINE_COMMENT_TYPE
        );

        return $lineCommentType === LineCommentType::REVIEW_COMMENT;
    }

    #[Override]
    public function publish(PublishableMessageInterface $publishableMessage): bool
    {
        if (!$this->supports($publishableMessage)) {
            throw new PublishingNotSupportedException(
                self::class,
                $publishableMessage
            );
        }

        /** @var PublishableLineCommentMessageCollection $publishableMessage */
        $messages = $publishableMessage->getMessages();
        $event = $publishableMessage->getEvent();

        try {
            $this->client->authenticateAsRepositoryOwner($event->getOwner());

            if (
                !$this->isCommitStillPullRequestHead(
                    $event->getOwner(),
                    $event->getRepository(),
                    (int)$event->getPullRequest(),
                    $event->getCommit()
                )
            ) {
                $this->reviewPublisherLogger->info(
                    sprintf(
                        '%s is no longer HEAD for %s, skipping review creation for %s.',
                        $event->getCommit(),
                        (string)$event->getPullRequest(),
                        (string)$event
                    )
                );

                return true;
            }

            $clearedSuccessfully = $this->clearExistingReviews(
                $event->getOwner(),
                $event->getRepository(),
                (int)$event->getPullRequest()
            );

            return $clearedSuccessfully &&
                $this->createReviewWithComments($event, $messages);
        } catch (ExceptionInterface $exception) {
            $this->reviewPublisherLogger->error(
                sprintf(
                    'Failed to publish review for %s',
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
     * Create a review with the given comments.
     *
     * This _will_ trigger a GitHub notification for any subscribers on the Pull Request,
     * so use with caution.
     *
     * @param PublishableLineCommentInterface[] $lineComments
     *
     * @throws ExceptionInterface
     */
    private function createReviewWithComments(
        EventInterface $event,
        array $lineComments
    ): bool {
        $this->client->pullRequest()
            ->reviews()
            ->create(
                $event->getOwner(),
                $event->getRepository(),
                (int)$event->getPullRequest(),
                [
                    'event' => 'COMMENT',
                    'body' => '',
                    'commit_id' => $event->getCommit(),
                    'comments' => $this->formatReviewComments($lineComments)
                ]
            );

        if ($this->client->getLastResponse()?->getStatusCode() !== Response::HTTP_OK) {
            $this->reviewPublisherLogger->critical(
                sprintf(
                    '%s status code returned while attempting to create review with comments.',
                    (string)$this->client->getLastResponse()?->getStatusCode()
                )
            );
            return false;
        }

        return true;
    }

    /**
     * Remove any existing reviews, and review comments, from the pull request.
     *
     * @throws ExceptionInterface
     */
    private function clearExistingReviews(
        string $owner,
        string $repository,
        int $pullRequest,
    ): bool {
        /** @var array{
         *     data: array{
         *          repository: array{
         *              pullRequest: array{
         *                  reviews: array{
         *                      nodes: list<array{
         *                          fullDatabaseId: string,
         *                          viewerDidAuthor: bool,
         *                          viewerCanDelete: bool,
         *                          comments: array{ totalCount: int }
         *                      }>
         *                  }
         *              }
         *          }
         *     }
         * } $response
         */
        $response = $this->client->graphql()
            ->execute(
                query: <<<GQL
                query getReviewComments(\$owner: String!, \$repository: String!, \$pullRequest: Int!) {
                    repository(owner: \$owner, name: \$repository) {
                        pullRequest(number: \$pullRequest) {
                            reviews(last: 100, states: [APPROVED, CHANGES_REQUESTED, COMMENTED]) {
                                nodes {
                                    fullDatabaseId
                                    viewerDidAuthor
                                    viewerCanDelete
                                    comments(first: 1) {
                                        totalCount
                                    }
                                }
                            }
                        }
                    }
                }
                GQL,
                variables: [
                    'owner' => $owner,
                    'repository' => $repository,
                    'pullRequest' => $pullRequest
                ]
            );

        if (!isset($response['data']['repository']['pullRequest']['reviews']['nodes'])) {
            $this->reviewPublisherLogger->info(
                'No reviews found for pull request.',
                [
                    'response' => $response
                ]
            );

            return true;
        }

        $successful = true;

        foreach ($response['data']['repository']['pullRequest']['reviews']['nodes'] as $existingReviewComment) {
            // The GitHub GraphQL API uses different native IDs than the REST API - meaning we explicitly
            // need to use the fullDatabaseId, as opposed to just the ID, to delete the review.
            $reviewId = $existingReviewComment['fullDatabaseId'];

            // To retrieve this information we need to use the GraphQL API, as the REST equivalent won't
            // provide explicit author data, outside of the basic user entity
            $didAuthor = $existingReviewComment['viewerDidAuthor'];
            $canDelete = $existingReviewComment['viewerCanDelete'];
            $totalComments = $existingReviewComment['comments']['totalCount'];

            if ($totalComments === 0) {
                // No comments on this review, so we can safely skip.
                continue;
            }

            if (!$didAuthor) {
                // The review wasn't authored by us, so we can safely skip.
                continue;
            }

            if (!$canDelete) {
                // The review was authored by us, but can't be deleted. In theory, this should never
                // happen. But regardless, if it does, theres nothing we can do.
                continue;
            }

            $hasDeleted = $this->deleteAllPullRequestComments(
                $owner,
                $repository,
                $pullRequest,
                (int)$reviewId
            );

            if (!$hasDeleted) {
                $this->reviewPublisherLogger->critical(
                    sprintf(
                        'Failed to delete review with ID: %s',
                        $reviewId
                    ),
                    [
                        'response' => $response
                    ]
                );

                $successful = false;
            }
        }

        return $successful;
    }

    /**
     * Delete all comments on a pull request review.
     *
     * There are options to delete or dismiss a review. However:
     * 1. Dismissing a review will not remove the comments - leaving a section in the PR for a
     *    previous (now outdated review)
     * 2. Deleting a review only works for pending reviews (i.e. submitted ones cannot be
     *    deleted)
     *
     * @param int $reviewId If using the GraphQL API this must be the fullDatabaseId
     */
    private function deleteAllPullRequestComments(
        string $owner,
        string $repository,
        int $pullRequest,
        int $reviewId
    ): bool {
        $paginator = $this->client->pagination(100);

        $comments = $paginator->fetchAllLazy(
            $this->client->pullRequest()
                ->reviews(),
            'comments',
            [
                $owner,
                $repository,
                $pullRequest,
                $reviewId
            ]
        );

        $successful = true;

        /** @var list<array{ id: string }> $comments */
        foreach ($comments as $comment) {
            $this->client->pullRequest()
                ->comments()
                ->remove(
                    $owner,
                    $repository,
                    (int)$comment['id']
                );

            if ($this->client->getLastResponse()?->getStatusCode() !== Response::HTTP_NO_CONTENT) {
                $this->reviewPublisherLogger->error(
                    sprintf(
                        '%s status code returned while attempting to delete a review on a pull request.',
                        (string)$this->client->getLastResponse()?->getStatusCode()
                    ),
                    [
                        'response' => $this->client->getLastResponse()
                    ]
                );

                $successful = false;
            }
        }

        return $successful;
    }

    /**
     * Format line comments into the review comment structure for GitHub.
     *
     * @param PublishableLineCommentInterface[] $comments
     *
     * @return array{
     *     path: string,
     *     line: int,
     *     body: string,
     *     side: 'RIGHT'
     * }[]
     */
    private function formatReviewComments(array $comments): array
    {
        return array_map(
            fn (PublishableLineCommentInterface $comment): array => [
                'path' => $comment->getFileName(),
                'line' => $comment->getStartLineNumber(),
                'side' => 'RIGHT',
                'body' => $this->templateRenderingService->render(
                    $comment,
                    TemplateVariant::LINE_COMMENT_BODY
                )
            ],
            $comments
        );
    }
}
