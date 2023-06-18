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
            // Trim the prefix attached by the unified diff ("b/")
            $file = substr($diff->getTo(), 2);

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
        $commit = $this->client->repo()
            ->commits()
            ->show(
                $owner,
                $repository,
                $sha
            );

        $files = $commit['files'] ?? [];

        if (!$files) {
            throw new RuntimeException('Unable to generate diff using commit.');
        }

        return array_reduce(
            $files,
            fn (string $patch, array $file) => <<<DIFF
                $patch
                --- a/{$file['filename']}
                +++ b/{$file['filename']}
                {$file['patch']}
                DIFF,
            ''
        );
    }

    public static function getProvider(): string
    {
        return Provider::GITHUB->value;
    }
}
