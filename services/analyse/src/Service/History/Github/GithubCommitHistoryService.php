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
 *                              edges: array{
 *                                  node: array{
 *                                      oid: string
 *                                  }
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

        do {
            $commitsPerPage = min(
                self::COMMITS_PER_PAGE,
                self::TOTAL_COMMITS - count($commits ?? [])
            );

            $historicCommits = $this->getHistoricCommits(
                $event->getOwner(),
                $event->getRepository(),
                $event->getRef(),
                $commitsPerPage,
                !isset($commits) ? $event->getCommit() : end($commits)
            );

            $commits = [
                ...($commits ?? []),
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
     * @return string[]
     */
    private function getHistoricCommits(
        string $owner,
        string $repository,
        string $ref,
        int $maxCommits,
        string $beforeCommit
    ): array {
        $result = $this->githubClient->graphql()
            ->execute(
                <<<GQL
                {
                  repository(owner: "{$owner}", name: "{$repository}") {
                    ref(qualifiedName: "{$ref}") {
                      name
                      target {
                        ... on Commit {
                          history(before: "{$beforeCommit}", first: {$maxCommits}) {
                            edges {
                              node {
                                oid
                              }
                            }
                            pageInfo {
                              hasPreviousPage
                              hasNextPage
                            }
                            totalCount
                          }
                        }
                      }
                    }
                  }
                }
                GQL
            );

        $result = $result['data']['repository']['ref']['target']['history']['edges'] ?? [];

        $commits = array_map(
            static function (array $commit) use ($beforeCommit): ?string {
                if (
                    isset($commit['node']['oid']) &&
                    $commit['node']['oid'] !== $beforeCommit
                ) {
                    /**
                     * The Github API will return the before commit (the one used as the cursor in the
                     * query), meaning we want to filter that out of the results, so that the returned
                     * commits are **only** those which preceding the cursor.
                     */
                    return $commit['node']['oid'];
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
