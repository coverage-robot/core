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

            $clearedSuccessfully = $this->updateExistingReviews(
                $event->getOwner(),
                $event->getRepository(),
                (int)$event->getPullRequest(),
                true,
            );

            $newReviewCreated = $this->createReviewWithComments(
                $event,
                $messages
            );

            // We still want to create the review, even if we failed to clear the
            // existing comments. But, if either fail, we should tell the caller.
            return $clearedSuccessfully && $newReviewCreated;
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
        if ($lineComments === []) {
            $this->reviewPublisherLogger->info(
                'No comments to publish for review.',
                [
                    'event' => $event
                ]
            );

            return true;
        }

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
     * Delete all comments on a pull request review.
     *
     * There are options to delete or dismiss a review. However:
     * 1. Dismissing a review will not remove the comments - leaving a section in the PR for a
     *    previous (now outdated review)
     * 2. Deleting a review only works for pending reviews (i.e. submitted ones cannot be
     *    deleted)
     *
     * @param int[] $reviewIds If using the GraphQL API this must be an array of integers from fullDatabaseId
     */
    private function updateExistingReviews(
        string $owner,
        string $repository,
        int $pullRequest,
        bool $shouldPreserveInteractedWithComments
    ): bool {
        /** @var array{
         *     data: array{
         *          repository: array{
         *              pullRequest: array{
         *                  reviewThreads: array{
         *                      nodes: list<array{
         *                          id: string,
         *                          viewerCanResolve: bool,
         *                          comments: array{
         *                              nodes: list<array{
         *                                  fullDatabaseId: int,
         *                                  reactions: array{ totalCount: int },
         *                                  pullRequestReview: array{
         *                                      viewerDidAuthor: bool,
         *                                      viewerCanDelete: bool
         *                                  }
         *                              }>,
         *                              totalCount: int
         *                          }
         *                      }>
         *                  }
         *              }
         *          }
         *     }
         * } $response
         */
        $response = $this->client->graphql()
            ->execute(
                <<<GQL
                query getReviewThreads(\$owner: String!, \$repository: String!, \$pullRequest: Int!) {
                    repository(owner: \$owner, name: \$repository) {
                        pullRequest(number: \$pullRequest) {
                            reviewThreads(last: 100) {
                                nodes {
                                    id
                                    viewerCanResolve
                                    comments(first: 1) {
                                        nodes {
                                            fullDatabaseId
                                            reactions {
                                                totalCount
                                            }
                                            pullRequestReview {
                                                viewerDidAuthor
                                                viewerCanDelete
                                            }
                                        }
                                        totalCount
                                    }
                                }
                            }
                        }
                    }
                }
                GQL,
                [
                    'owner' => $owner,
                    'repository' => $repository,
                    'pullRequest' => $pullRequest
                ]
            );

        if (!isset($response['data']['repository']['pullRequest']['reviewThreads']['nodes'])) {
            $this->reviewPublisherLogger->info(
                sprintf(
                    'No reviews found for pull request %s.',
                    $pullRequest
                ),
                [
                    'response' => $response
                ]
            );

            return true;
        }

        $commentsToDelete = [];
        $threadsToResolve = [];

        foreach ($response['data']['repository']['pullRequest']['reviewThreads']['nodes'] as $thread) {
            $leadingComment = $thread['comments']['nodes'][0] ?? null;
            $review = $leadingComment['pullRequestReview'] ?? null;

            if ($review === null) {
                continue;
            }

            if ($leadingComment === null) {
                continue;
            }

            // To retrieve this information we need to use the GraphQL API, as the REST equivalent won't
            // provide explicit author data, outside of the basic user entity
            $didAuthor = $review['viewerDidAuthor'];
            $canDelete = $review['viewerCanDelete'];

            if (!$didAuthor) {
                // The review wasn't authored by us, so we can safely skip.
                continue;
            }

            if (!$canDelete) {
                // The review was authored by us, but can't be deleted. In theory, this should never
                // happen. But regardless, if it does, theres nothing we can do.
                continue;
            }

            $threadId = $thread['id'];
            $leadingCommentId = $leadingComment['fullDatabaseId'];

            $canResolve = $thread['viewerCanResolve'];

            $hasBeenInteractedWith = $thread['comments']['totalCount'] > 1 ||
                $leadingComment['reactions']['totalCount'] > 0;

            if (!$hasBeenInteractedWith || !$shouldPreserveInteractedWithComments) {
                $commentsToDelete[] = $leadingCommentId;
            } elseif ($canResolve) {
                $threadsToResolve[] = $threadId;
            }
        }

        $commentsDeleted = $this->deleteReviewThreadComments(
            $owner,
            $repository,
            $commentsToDelete
        );
        $threadsResolved = $this->resolveReviewThreads($threadsToResolve);

        return $commentsDeleted && $threadsResolved;
    }

    /**
     * @param int[] $commentIds
     */
    private function deleteReviewThreadComments(
        string $owner,
        string $repository,
        array $commentIds
    ): bool {
        $successful = true;
        $comments = $this->client->pullRequest()
            ->comments();

        foreach ($commentIds as $commentId) {
            $comments->remove($owner, $repository, $commentId);

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

        if (!$successful) {
            return false;
        }

        $this->reviewPublisherLogger->info(
            sprintf(
                'Successfully resolved all %s of the threads.',
                count($commentIds)
            ),
            [
                'commentIds' => $commentIds
            ]
        );

        return true;
    }

    /**
     * @param string[] $threadIds
     */
    private function resolveReviewThreads(array $threadIds): bool
    {
        $successful = true;

        $api = $this->client->graphql();

        foreach ($threadIds as $threadId) {
            /** @var array{
             *     data: array{
             *          resolveReviewThread: array{
             *              thread: array{
             *                  id: string
             *              }
             *          }
             *     }
             * } $response
             */
            $response = $api->execute(
                query: <<<GQL
                mutation resolveThread(\$threadId: ID!) {
                    resolveReviewThread(input: {threadId: \$threadId}) {
                        thread {
                            id
                        }
                   }
                }
                GQL,
                variables: [
                    'threadId' => $threadId
                ]
            );

            $successful = $successful &&
                isset($response['data']['resolveReviewThread']['thread']['id']) &&
                $response['data']['resolveReviewThread']['thread']['id'] === $threadId;
        }

        if (!$successful) {
            $this->reviewPublisherLogger->info(
                'Not all threads were deleted successfully.',
                [
                    'threadIds' => $threadIds
                ]
            );

            return false;
        }

        $this->reviewPublisherLogger->info(
            sprintf(
                'Successfully resolved all %s of the threads.',
                count($threadIds)
            ),
            [
                'threadIds' => $threadIds
            ]
        );

        return true;
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
