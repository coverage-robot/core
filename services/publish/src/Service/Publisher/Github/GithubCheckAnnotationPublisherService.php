<?php

namespace App\Service\Publisher\Github;

use App\Exception\PublishException;
use App\Service\EnvironmentService;
use App\Service\Formatter\CheckAnnotationFormatterService;
use App\Service\Formatter\CheckRunFormatterService;
use Packages\Clients\Client\Github\GithubAppInstallationClient;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\PublishableMessage\PublishableCheckAnnotationMessage;
use Packages\Models\Model\PublishableMessage\PublishableCheckAnnotationMessageCollection;
use Packages\Models\Model\PublishableMessage\PublishableMessageInterface;
use Packages\Models\Model\Upload;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

class GithubCheckAnnotationPublisherService extends AbstractGithubCheckPublisherService
{
    public function __construct(
        private readonly CheckRunFormatterService $checkRunFormatterService,
        private readonly CheckAnnotationFormatterService $formatter,
        GithubAppInstallationClient $client,
        EnvironmentService $environmentService,
        LoggerInterface $checkPublisherLogger
    ) {
        parent::__construct($client, $environmentService, $checkPublisherLogger);
    }

    public function supports(PublishableMessageInterface $publishableMessage): bool
    {
        if (
            !$publishableMessage instanceof PublishableCheckAnnotationMessageCollection &&
            !$publishableMessage instanceof PublishableCheckAnnotationMessage
        ) {
            return false;
        }

        if (!$publishableMessage->getUpload()->getPullRequest()) {
            return false;
        }

        return $publishableMessage->getUpload()->getProvider() === Provider::GITHUB;
    }

    /**
     * Publish annotations against all uncovered lines which were added in the PR (or commits) diff.
     */
    public function publish(PublishableMessageInterface $publishableMessage): bool
    {
        if (!$this->supports($publishableMessage)) {
            throw PublishException::notSupportedException();
        }

        /** @var PublishableCheckAnnotationMessageCollection|PublishableCheckAnnotationMessage $publishableMessage */
        $upload = $publishableMessage->getUpload();

        $this->client->authenticateAsRepositoryOwner($upload->getOwner());

        $checkRun = $this->getCheckRun(
            $upload->getOwner(),
            $upload->getRepository(),
            $upload->getCommit()
        );

        return $this->addAnnotations($checkRun, $upload, $publishableMessage);
    }

    private function addAnnotations(
        int $checkRun,
        Upload $upload,
        PublishableCheckAnnotationMessageCollection|PublishableCheckAnnotationMessage $annotationMessage
    ): bool {
        $annotations = $this->getFormattedAnnotations($annotationMessage);

        $chunkedAnnotations = array_chunk($annotations, 50);
        foreach ($chunkedAnnotations as $chunk) {
            $this->client->repo()
                ->checkRuns()
                ->update(
                    $upload->getOwner(),
                    $upload->getRepository(),
                    $checkRun,
                    [
                        'output' => [
                            'title' => $this->checkRunFormatterService->formatTitle(),
                            'summary' => $this->checkRunFormatterService->formatSummary(),
                            'annotations' => $chunk
                        ]
                    ]
                );

            if ($this->client->getLastResponse()?->getStatusCode() !== Response::HTTP_OK) {
                $this->checkPublisherLogger->critical(
                    sprintf(
                        '%s status code returned while attempting to update existing check run with new results.',
                        (string)$this->client->getLastResponse()?->getStatusCode()
                    )
                );
            }
        }

        return true;
    }

    private function getFormattedAnnotations(
        PublishableCheckAnnotationMessageCollection|PublishableCheckAnnotationMessage $publishableMessage
    ): array {
        $annotations = $publishableMessage instanceof PublishableCheckAnnotationMessageCollection
            ? $publishableMessage->getAnnotations()
            : [$publishableMessage];

        $annotations = array_map(
            fn(PublishableCheckAnnotationMessage $annotation) => [
                'path' => $annotation->getFileName(),
                'annotation_level' => 'warning',
                'title' => $this->formatter->formatTitle($annotation),
                'message' => $this->formatter->format($annotation),
                'start_line' => $annotation->getLineNumber(),
                'end_line' => $annotation->getLineNumber()
            ],
            $annotations
        );

        return array_filter($annotations);
    }
}
