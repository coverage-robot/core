<?php

namespace App\Service\Diff\Github;

use App\Model\ReportWaypoint;
use App\Service\Diff\DiffParserServiceInterface;
use App\Service\ProviderAwareInterface;
use Override;
use Packages\Clients\Client\Github\GithubAppInstallationClient;
use Packages\Contracts\Provider\Provider;
use Psr\Log\LoggerInterface;
use SebastianBergmann\Diff\Line;
use SebastianBergmann\Diff\Parser;

class GithubDiffParserService implements DiffParserServiceInterface, ProviderAwareInterface
{
    public function __construct(
        private readonly GithubAppInstallationClient $client,
        private readonly Parser $parser,
        private readonly LoggerInterface $diffParserLogger
    ) {
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function get(ReportWaypoint $waypoint): array
    {
        $this->client->authenticateAsRepositoryOwner($waypoint->getOwner());

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

        $diff = $pullRequest ?
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
     *
     * This request is fairly straightforward as the full diff can be fetched (e.g. it's not
     * just the patch between each file).
     */
    private function getPullRequestDiff(string $owner, string $repository, int $pullRequest): string
    {
        $this->diffParserLogger->info(
            sprintf('Fetching pull request diff for %s pull request in %s repository.', $pullRequest, $repository),
            [
                'owner' => $owner,
                'repository' => $repository,
                'pullRequest' => $pullRequest
            ]
        );

        /** @var string $diff */
        $diff = $this->client->pullRequest()
            ->configure('diff')
            ->show(
                $owner,
                $repository,
                $pullRequest
            );

        return $diff;
    }

    /**
     * Retrieve the unified diff from a particular commit in GitHub.
     *
     * This request is slightly more tricky as the API only provides patch diffs
     * for each file, which means the patches needs to be joined together.
     */
    private function getCommitDiff(string $owner, string $repository, string $sha): string
    {
        $this->diffParserLogger->info(
            sprintf('Fetching commit diff for %s commit in %s repository.', $sha, $repository),
            [
                'owner' => $owner,
                'repository' => $repository,
                'commit' => $sha
            ]
        );

        /** @var array<array-key, object{ files: array }> $commit */
        $commit = $this->client->repo()
            ->commits()
            ->show(
                $owner,
                $repository,
                $sha
            );

        /** @var array<array-key, array> $files */
        $files = $commit['files'] ?? [];

        return array_reduce(
            $files,
            static fn(string $diff, array $file) => <<<DIFF
                $diff
                --- a/{$file['filename']}
                +++ b/{$file['filename']}
                {$file['patch']}
                DIFF,
            ''
        );
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
}
