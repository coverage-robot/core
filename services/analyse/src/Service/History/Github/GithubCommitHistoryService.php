<?php

namespace App\Service\History\Github;

use App\Service\History\CommitHistoryServiceInterface;
use App\Service\ProviderAwareInterface;
use Packages\Clients\Client\Github\GithubAppInstallationClient;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Event\EventInterface;

/**
 * @psalm-type Result = array{
 *     data: array{
 *          repository: array{
 *                  ref: array{
 *                      target: array{
 *                          history: array{
 *                              nodes: array{
 *                                  oid: string
 *                              }[]
 *                          }
 *                      }
 *                  }
 *              }
 *          }
 *     }
 */
class GithubCommitHistoryService implements CommitHistoryServiceInterface, ProviderAwareInterface
{
    /**
     * The total number of commits to carry forward coverage from in the commit tree
     */
    private const TOTAL_COMMITS = 200;

    /**
     * The number of commits the GitHub GraphQL API can support per page
     */
    private const COMMITS_PER_PAGE = 100;

    public function __construct(private readonly GithubAppInstallationClient $githubClient)
    {
    }

    /**
     * @inheritDoc
     */
    public function getPrecedingCommits(EventInterface $event): array
    {
        $this->githubClient->authenticateAsRepositoryOwner($event->getOwner());

        /** @var string[] $commits */
        $commits = [];

        do {
            $commitsPerPage = min(
                self::COMMITS_PER_PAGE,
                self::TOTAL_COMMITS - count($commits)
            );

            $historicCommits = $this->getHistoricCommits(
                $event->getOwner(),
                $event->getRepository(),
                $event->getRef(),
                $commitsPerPage,
                empty($commits) ? $event->getCommit() : end($commits)
            );

            $commits = [
                ...$commits,
                ...$historicCommits
            ];

            if (count($historicCommits) < $commitsPerPage - 1) {
                // We must be on the last page, as the results returned from the API are
                // one less than the total commits per page provided (one less, because the first
                // result will be the before commit we provided, which will have been
                // filtered out)
                break;
            }
        } while (count($commits) < self::TOTAL_COMMITS);

        return $commits;
    }

    /**
     * The cursor GitHub's GraphQL API uses follows the pattern of:
     *
     * ```<starting commit SHA> <number of preceding commits>```
     *
     * (i.e. 3a6d549ba8bba3987d04fa6ae7b861e8e054968e8 100)
     *
     * This method makes a compatible cursor which allows us to paginate
     * through the API, fetching all of the preceding commits up the tree with
     * a predictable response.
     */
    private function makeCursor(string $lastCommit, int $commitsPerPage): string
    {
        return sprintf(
            '%s %s',
            $lastCommit,
            $commitsPerPage
        );
    }

    /**
     * @return string[]
     */
    private function getHistoricCommits(
        string $owner,
        string $repository,
        string $ref,
        int $commitsPerPage,
        string $beforeCommit
    ): array {
        /** @var Result $result */
        $result = $this->githubClient->graphql()
            ->execute(
                <<<GQL
                {
                  repository(owner: "{$owner}", name: "{$repository}") {
                    ref(qualifiedName: "{$ref}") {
                      name
                      target {
                        ... on Commit {
                          history(before: "{$this->makeCursor($beforeCommit, $commitsPerPage)}", last: {$commitsPerPage}) {
                            nodes {
                              oid
                            }
                          }
                        }
                      }
                    }
                  }
                }
                GQL
            );

        $result = $result['data']['repository']['ref']['target']['history']['nodes'] ?? [];

        $commits = array_map(
            static function (array $commit) use ($beforeCommit): ?string {
                if (
                    isset($commit['oid']) &&
                    $commit['oid'] !== $beforeCommit
                ) {
                    /**
                     * The Github API will return the before commit (the one used as the cursor in the
                     * query), meaning we want to filter that out of the results, so that the returned
                     * commits are **only** those which preceding the cursor.
                     */
                    return $commit['oid'];
                }

                return null;
            },
            $result
        );

        // Remove any nulls and re-index the array
        return array_values(array_filter($commits));
    }

    public static function getProvider(): string
    {
        return Provider::GITHUB->value;
    }
}
