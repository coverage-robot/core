<?php

namespace App\Service\Diff\Github;

use App\Exception\CommitDiffException;
use App\Model\ReportWaypoint;
use App\Service\Diff\DiffParserServiceInterface;
use Generator;
use Github\Exception\ExceptionInterface;
use Override;
use Packages\Clients\Client\Github\GithubAppInstallationClientInterface;
use Packages\Contracts\Provider\Provider;
use Packages\Contracts\Provider\ProviderAwareInterface;
use Packages\Telemetry\Service\MetricServiceInterface;
use Psr\Log\LoggerInterface;
use SebastianBergmann\Diff\Line;
use SebastianBergmann\Diff\Parser;

final class GithubDiffParserService implements DiffParserServiceInterface, ProviderAwareInterface
{
    public function __construct(
        private readonly GithubAppInstallationClientInterface $client,
        private readonly Parser $parser,
        private readonly LoggerInterface $diffParserLogger,
        private readonly MetricServiceInterface $metricService
    ) {
    }

    /**
     * @inheritDoc
     * @throws CommitDiffException
     */
    #[Override]
    public function get(ReportWaypoint $waypoint): array
    {
        $pullRequest = $waypoint->getPullRequest();

        $this->diffParserLogger->info(
            sprintf('Fetching diff from GitHub for %s.', (string)$waypoint),
            [
                'owner' => $waypoint->getOwner(),
                'repository' => $waypoint->getRepository(),
                'commit' => $waypoint->getCommit(),
                'pull_request' => $pullRequest
            ]
        );

        $this->metricService->increment(
            metric: 'DiffRetrievalRequest',
            dimensions: [['provider', 'owner']],
            properties: ['provider' => Provider::GITHUB->value, 'owner' => $waypoint->getOwner()]
        );

        try {
            $diff = $pullRequest !== null ?
                $this->getPullRequestDiff(
                    $waypoint->getOwner(),
                    $waypoint->getRepository(),
                    (int)$pullRequest
                ) :
                $this->getCommitDiff(
                    $waypoint->getOwner(),
                    $waypoint->getRepository(),
                    $waypoint->getCommit()
                );
        } catch (ExceptionInterface $exception) {
            throw new CommitDiffException(
                sprintf(
                    'Failed to retrieve diff for %s',
                    (string)$waypoint
                ),
                previous: $exception
            );
        }

        $files = $this->parser->parse($diff);

        $addedLines = [];

        foreach ($files as $diff) {
            $file = $this->trimPrefix($diff->to());

            foreach ($diff->chunks() as $chunk) {
                $offset = 0;

                foreach ($chunk->lines() as $line) {
                    if ($line->type() === Line::ADDED) {
                        $addedLines[$file] = [
                            ...($addedLines[$file] ?? []),
                            $chunk->end() + $offset
                        ];
                    }

                    if ($line->type() !== Line::REMOVED) {
                        ++$offset;
                    }
                }
            }
        }

        $this->diffParserLogger->info(
            sprintf('Diff for %s has %s files with added lines.', (string)$waypoint, count($addedLines)),
            [
                'owner' => $waypoint->getOwner(),
                'repository' => $waypoint->getRepository(),
                'commit' => $waypoint->getCommit(),
                'pullRequest' => $pullRequest,
                $addedLines
            ]
        );

        return $addedLines;
    }

    #[Override]
    public static function getProvider(): string
    {
        return Provider::GITHUB->value;
    }

    /**
     * Retrieve the unified diff from a particular pull request in GitHub.
     */
    private function getPullRequestDiff(string $owner, string $repository, int $pullRequest): string
    {
        $this->client->authenticateAsRepositoryOwner($owner);

        $this->diffParserLogger->info(
            sprintf('Fetching pull request diff for %s pull request in %s repository.', $pullRequest, $repository),
            [
                'owner' => $owner,
                'repository' => $repository,
                'pullRequest' => $pullRequest
            ]
        );

        /** @var Generator<array{filename: string, patch?: string}> $files */
        $files = $this->client->pagination(100)
            ->fetchAllLazy(
                $this->client->pullRequest(),
                'files',
                [
                    $owner,
                    $repository,
                    $pullRequest
                ]
            );

        return $this->mergePatchesIntoUnifiedDiff($files);
    }

    /**
     * Retrieve the unified diff from a particular commit in GitHub.
     */
    private function getCommitDiff(string $owner, string $repository, string $sha): string
    {
        $this->client->authenticateAsRepositoryOwner($owner);

        $this->diffParserLogger->info(
            sprintf('Fetching commit diff for %s commit in %s repository.', $sha, $repository),
            [
                'owner' => $owner,
                'repository' => $repository,
                'commit' => $sha
            ]
        );

        /** @var array<array-key, object{ files?: list{ array{filename: string, patch?: string} } }> $commit */
        $commit = $this->client->repo()
            ->commits()
            ->show(
                $owner,
                $repository,
                $sha
            );

        return $this->mergePatchesIntoUnifiedDiff($commit['files'] ?? []);
    }

    /**
     * Trim the prefix which unified diffs apply to the beginning of the file path.
     *
     * These are either "a/" or "b/", depending on whether the file is the original,
     * or the new version.
     */
    private function trimPrefix(string $path): string
    {
        if (str_starts_with($path, 'a/') || str_starts_with($path, 'b/')) {
            return substr($path, 2);
        }

        return $path;
    }

    /**
     * @param Generator<array{ filename: string, patch?: string }>|list{ array{ filename: string, patch?: string } } $files
     * @return string
     */
    private function mergePatchesIntoUnifiedDiff(Generator|array $files): string
    {
        $diff = '';

        foreach ($files as $file) {
            /**
             * Default files without a patch to show as empty. These will be binary files or similar
             * where the patch isn't available. In these cases, we're not interested in the diff anyway
             * as there'll be no coverage information related to these lines.
             *
             * @see https://docs.github.com/en/rest/commits/commits?apiVersion=2022-11-28#get-a-commit
             *
             * @var array{filename: string, patch?: string} $file
             */
            $patch = $file['patch'] ?? '';

            $diff = <<<DIFF
                $diff
                --- a/{$file['filename']}
                +++ b/{$file['filename']}
                {$patch}
                DIFF;
        }

        return $diff;
    }
}
