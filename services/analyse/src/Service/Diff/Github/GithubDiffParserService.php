<?php

namespace App\Service\Diff\Github;

use App\Client\Github\GithubAppInstallationClient;
use App\Service\Diff\DiffParserServiceInterface;
use App\Service\ProviderAwareInterface;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Upload;
use Psr\Log\LoggerInterface;
use RuntimeException;
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
    public function get(Upload $upload): array
    {
        $this->client->authenticateAsRepositoryOwner($upload->getOwner());

        $this->diffParserLogger->info(
            sprintf('Fetching diff from GitHub for %s.', (string)$upload),
            [
                'owner' => $upload->getOwner(),
                'repository' => $upload->getRepository(),
                'commit' => $upload->getCommit(),
                'pull_request' => $upload->getPullRequest()
            ]
        );

        $diff = $upload->getPullRequest() ?
            $this->getPullRequestDiff(
                $upload->getOwner(),
                $upload->getRepository(),
                (int)$upload->getPullRequest()
            ) :
            $this->getCommitDiff(
                $upload->getOwner(),
                $upload->getRepository(),
                $upload->getCommit()
            );

        $files = $this->parser->parse($diff);

        $addedLines = [];

        foreach ($files as $diff) {
            $file = $this->trimPrefix($diff->getTo());

            foreach ($diff->getChunks() as $chunk) {
                $offset = 0;

                foreach ($chunk->getLines() as $line) {
                    if ($line->getType() === Line::ADDED) {
                        $addedLines[$file] = [
                            ...($addedLines[$file] ?? []),
                            $chunk->getEnd() + $offset
                        ];
                    }

                    if ($line->getType() !== Line::REMOVED) {
                        $offset++;
                    }
                }
            }
        }

        $this->diffParserLogger->info(
            sprintf('Diff for %s has %s files with added lines.', (string)$upload, count($addedLines)),
            [
                'owner' => $upload->getOwner(),
                'repository' => $upload->getRepository(),
                'commit' => $upload->getCommit(),
                'pullRequest' => $upload->getPullRequest(),
                $addedLines
            ]
        );

        return $addedLines;
    }

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

        if (empty($files)) {
            throw new RuntimeException(
                sprintf('Unable to generate diff using commit for %s in %s repository.', $sha, $repository)
            );
        }

        return array_reduce(
            $files,
            fn (string $diff, array $file) => <<<DIFF
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
