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
        $paginator = $this->client->pagination(100);

        /** @var array{ id: int, performed_via_github_app?: array{ id: string }}[] $existingReviewComments */
        $existingReviewComments = $paginator->fetchAllLazy(
            $this->client->pullRequest()
                ->comments(),
            'all',
            [
                $owner,
                $repository,
                $pullRequest
            ]
        );

        if ($this->client->getLastResponse()?->getStatusCode() !== Response::HTTP_OK) {
            $this->reviewPublisherLogger->critical(
                sprintf(
                    '%s status code returned while attempting to list all review.',
                    (string)$this->client->getLastResponse()?->getStatusCode()
                )
            );
            return false;
        }

        $successful = true;

        $appId = $this->environmentService->getVariable(EnvironmentVariable::GITHUB_APP_ID);

        foreach ($existingReviewComments as $existingReviewComment) {
            $existingId = $existingReviewComment['id'];
            $reviewCommentAppId = $existingReviewComment['performed_via_github_app']['id'] ?? null;

            if ($reviewCommentAppId !== $appId) {
                // Review comment wasn't created by us, so we can skip it.
                continue;
            }

            $this->client->pullRequest()
                ->comments()
                ->remove(
                    $owner,
                    $repository,
                    $existingId
                );

            $successful = $successful &&
                $this->client->getLastResponse()?->getStatusCode() === Response::HTTP_NO_CONTENT;
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
