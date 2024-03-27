<?php

namespace App\Service\Publisher\Github;

use App\Enum\TemplateVariant;
use App\Exception\CheckRunNotFoundException;
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
use Packages\Contracts\Provider\Provider;
use Packages\Contracts\PublishableMessage\PublishableMessageInterface;
use Packages\Message\PublishableMessage\PublishableLineCommentInterface;
use Packages\Message\PublishableMessage\PublishableLineCommentMessageCollection;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

final class GithubAnnotationPublisherService implements PublisherServiceInterface
{
    use GithubCheckRunAwareTrait;

    private const int MAX_ANNOTATIONS_PER_CHECK_RUN = 50;

    public function __construct(
        private readonly TemplateRenderingService $templateRenderingService,
        private readonly SettingServiceInterface $settingService,
        private readonly GithubAppInstallationClientInterface $client,
        private readonly EnvironmentServiceInterface $environmentService,
        private readonly LoggerInterface $checkPublisherLogger
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

        /** @var LineCommentType $lineCommentType */
        $lineCommentType = $this->settingService->get(
            $event->getProvider(),
            $publishableMessage->getEvent()
                ->getOwner(),
            $publishableMessage->getEvent()
                ->getRepository(),
            SettingKey::LINE_COMMENT_TYPE
        );

        return $lineCommentType === LineCommentType::ANNOTATION;
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
        $event = $publishableMessage->getEvent();

        /** @var PublishableLineCommentInterface[] $comments */
        $comments = $publishableMessage->getMessages();

        try {
            $this->client->authenticateAsRepositoryOwner($event->getOwner());

            $checkRun = $this->getCheckRun(
                $event->getOwner(),
                $event->getRepository(),
                $event->getCommit()
            );

            $successful = true;

            foreach ($this->formatAndBatchAnnotations($comments) as $annotations) {
                $this->client->repo()
                    ->checkRuns()
                    ->update(
                        $event->getOwner(),
                        $event->getRepository(),
                        $checkRun['id'],
                        [
                            'output' => [
                                'title' => $checkRun['output']['title'],
                                'summary' => $checkRun['output']['summary'],
                                'annotations' => $annotations,
                            ]
                        ]
                    );

                if ($this->client->getLastResponse()?->getStatusCode() !== Response::HTTP_OK) {
                    $this->checkPublisherLogger->critical(
                        sprintf(
                            '%s status code returned while attempting to update check run with new annotations.',
                            (string)$this->client->getLastResponse()?->getStatusCode()
                        )
                    );

                    $successful = false;
                }
            }

            return $successful;
        } catch (ExceptionInterface $exception) {
            $this->checkPublisherLogger->critical(
                sprintf(
                    'Exception while writing annotations to check run for %s',
                    (string)$publishableMessage->getEvent()
                ),
                [
                    'exception' => $exception
                ]
            );
            return false;
        } catch (CheckRunNotFoundException $exception) {
            // As we've enforced the annotations as a lower priority (and therefore executed
            // after the check run has been published), we should never hit this exception.
            $this->checkPublisherLogger->critical(
                sprintf(
                    'Failed to find existing check run to publish annotations to: %s',
                    (string)$publishableMessage->getEvent()
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
        return GithubCheckRunPublisherService::getPriority() - 1;
    }

    /**
     * @param PublishableLineCommentInterface[] $comments
     *
     * @return iterable<array>
     */
    private function formatAndBatchAnnotations(array $comments): iterable
    {
        $annotations = [];

        foreach ($comments as $comment) {
            if (count($annotations) === self::MAX_ANNOTATIONS_PER_CHECK_RUN) {
                yield $annotations;

                $annotations = [];
            }

            $annotations[] = [
                'path' => $comment->getFileName(),
                'annotation_level' => 'warning',
                'title' => $this->templateRenderingService->render(
                    $comment,
                    TemplateVariant::LINE_COMMENT_TITLE
                ),
                'message' => $this->templateRenderingService->render(
                    $comment,
                    TemplateVariant::LINE_COMMENT_BODY
                ),
                'start_line' => $comment->getStartLineNumber(),

                // We want to place the annotation on the starting line, as opposed to spreading
                // across the start and end. If this was the end line number (i.e. 14, the annotation
                // would end up on line 14, as opposed to where the annotation actually started).
                'end_line' => $comment->getStartLineNumber()
            ];
        }

        if ($annotations !== []) {
            yield $annotations;
        }
    }
}
