<?php

namespace App\Service\Diff\Github;

use App\Client\Github\GithubAppInstallationClient;
use App\Service\Diff\DiffParserServiceInterface;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Upload;
use RuntimeException;
use SebastianBergmann\Diff\Line;
use SebastianBergmann\Diff\Parser;

class GithubDiffParserService implements DiffParserServiceInterface
{
    public function __construct(
        private readonly GithubAppInstallationClient $client,
        private readonly Parser $parser
    ) {
    }

    /**
     * @inheritDoc
     */
    public function get(Upload $upload): array
    {
        $this->client->authenticateAsRepositoryOwner($upload->getOwner());

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
                            $chunk->getStart() + $offset
                        ];
                    }

                    if ($line->getType() !== Line::REMOVED) {
                        $offset++;
                    }
                }
            }
        }

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
            throw new RuntimeException('Unable to generate diff using commit.');
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
