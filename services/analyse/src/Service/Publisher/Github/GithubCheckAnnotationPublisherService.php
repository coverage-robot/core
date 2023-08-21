<?php

namespace App\Service\Publisher\Github;

use App\Client\Github\GithubAppInstallationClient;
use App\Exception\PublishException;
use App\Model\PublishableCoverageDataInterface;
use App\Query\Result\LineCoverageQueryResult;
use App\Service\EnvironmentService;
use App\Service\Formatter\CheckAnnotationFormatterService;
use Packages\Models\Enum\LineState;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Upload;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

class GithubCheckAnnotationPublisherService extends AbstractGithubCheckPublisherService
{
    public function __construct(
        GithubAppInstallationClient $client,
        EnvironmentService $environmentService,
        LoggerInterface $checkPublisherLogger,
        private readonly CheckAnnotationFormatterService $formatter
    ) {
        parent::__construct($client, $environmentService, $checkPublisherLogger);
    }

    public function supports(Upload $upload, PublishableCoverageDataInterface $coverageData): bool
    {
        if (!$upload->getPullRequest()) {
            return false;
        }

        return $upload->getProvider() === Provider::GITHUB;
    }


    /**
     * Publish annotations against all uncovered lines which were added in the PR (or commits) diff.
     */
    public function publish(Upload $upload, PublishableCoverageDataInterface $coverageData): bool
    {
        if (!$this->supports($upload, $coverageData)) {
            throw PublishException::notSupportedException();
        }

        $this->client->authenticateAsRepositoryOwner($upload->getOwner());

        $checkRun = $this->getCheckRun(
            $upload->getOwner(),
            $upload->getRepository(),
            $upload->getCommit()
        );

        return $this->addAnnotations($checkRun, $upload, $coverageData);
    }

    private function addAnnotations(
        int $checkRun,
        Upload $upload,
        PublishableCoverageDataInterface $coverageData
    ): bool {
        $annotations = $this->getFormattedAnnotations($coverageData);

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
                            'title' => 'Coverage',
                            'summary' => 'Coverage Analysis Complete.',
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

    private function getFormattedAnnotations(PublishableCoverageDataInterface $coverageData): array
    {
        $formatter = $this->formatter;

        $annotations = array_map(
            function (LineCoverageQueryResult $line) use ($formatter) {
                if ($line->getState() !== LineState::UNCOVERED) {
                    return null;
                }

                return [
                    'path' => $line->getFileName(),
                    'annotation_level' => 'warning',
                    'title' => 'Uncovered Line',
                    'message' => $formatter->format($line),
                    'start_line' => $line->getLineNumber(),
                    'end_line' => $line->getLineNumber()
                ];
            },
            $coverageData->getDiffLineCoverage()->getLines()
        );

        return array_filter($annotations);
    }

    public static function getPriority(): int
    {
        // The check run should **always** be published after the Check run comment
        return GithubCheckRunPublisherService::getPriority() - 1;
    }
}
