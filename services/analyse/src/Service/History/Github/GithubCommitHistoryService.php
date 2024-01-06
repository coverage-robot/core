<?php

namespace App\Service\History\Github;

use App\Model\ReportWaypoint;
use App\Service\History\CommitHistoryService;
use App\Service\History\CommitHistoryServiceInterface;
use App\Service\ProviderAwareInterface;
use Packages\Clients\Client\Github\GithubAppInstallationClient;
use Packages\Contracts\Event\EventInterface;
use Packages\Contracts\Provider\Provider;
use Psr\Log\LoggerInterface;

/**
 * @psalm-type History = array{
 *     data: array{
 *          repository: array{
 *                  ref: array{
 *                      target: array{
 *                          history: array{
 *                              nodes: array{
 *                                  oid: string,
 *                                  associatedPullRequests: array{
 *                                      nodes: array{
 *                                          merged: bool,
 *                                          headRefName: string
 *                                      }[]
 *                                  }
 *                              }[]
 *                          }
 *                      }
 *                  }
 *              }
 *          }
 *     }
 *
 *  @psalm-type Refs = array{
 *      data: array{
 *           repository: array{
 *                   refs: array{
 *                       nodes: array{
 *                           name: string
 *                       }
 *                   }
 *               }
 *           }
 *      }
 */
class GithubCommitHistoryService implements CommitHistoryServiceInterface, ProviderAwareInterface
{
    public function __construct(
        private readonly GithubAppInstallationClient $githubClient,
        private readonly LoggerInterface $githubHistoryLogger
    ) {
    }

    /**
     * @inheritDoc
     *
     * @return array{commit: string, merged: bool, ref: string|null}[]
     */
    public function getPrecedingCommits(EventInterface|ReportWaypoint $waypoint, int $page = 1): array
    {
        $this->githubClient->authenticateAsRepositoryOwner($waypoint->getOwner());

        $offset = (max(1, $page) * CommitHistoryService::COMMITS_TO_RETURN_PER_PAGE) + 1;

        // Check if the current ref exists, or whether we can (and should) substitute
        // the base ref in for lookup
        $baseRef = $waypoint->getBaseRef();
        $shouldUseCurrentRef = !$baseRef || $this->doesRefExist(
            $waypoint->getOwner(),
            $waypoint->getRepository(),
            $waypoint->getRef()
        );

        $commits = $this->getHistoricCommits(
            $waypoint->getOwner(),
            $waypoint->getRepository(),
            $shouldUseCurrentRef ?
                $waypoint->getRef() :
                $baseRef,
            $waypoint->getCommit(),
            $offset
        );

        $this->githubHistoryLogger->info(
            sprintf(
                'Fetched %s preceding commits from GitHub for %s',
                CommitHistoryService::COMMITS_TO_RETURN_PER_PAGE,
                (string)$waypoint
            ),
            [
                'commitsToRetrieve' => CommitHistoryService::COMMITS_TO_RETURN_PER_PAGE,
                'offset' => $offset,
                'commits' => $commits,
            ]
        );

        return $commits;
    }

    /**
     * @return array{commit: string, merged: bool, ref: string|null}[]
     */
    private function getHistoricCommits(
        string $owner,
        string $repository,
        string $ref,
        string $beforeCommit,
        int $offset
    ): array {
        $commitsPerPage = CommitHistoryService::COMMITS_TO_RETURN_PER_PAGE;

        /** @var History $result */
        $result = $this->githubClient->graphql()
            ->execute(
                <<<GQL
                {
                    repository(owner: "{$owner}", name: "{$repository}") {
                        ref(qualifiedName: "{$ref}") {
                            name
                            target {
                                ... on Commit {
                                    history(
                                        before: "{$this->makeCursor($beforeCommit, $offset)}",
                                        last: {$commitsPerPage}
                                    ) {
                                        nodes {
                                            oid
                                            associatedPullRequests(last: 1) {
                                                nodes {
                                                    merged,
                                                    headRefName
                                                }
                                            }
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

        return array_map(
            static fn(array $commit): array => [
                'commit' => $commit['oid'],
                'merged' => $commit['associatedPullRequests']['nodes'][0]['merged'] ?? true,
                'ref' => $commit['associatedPullRequests']['nodes'][0]['headRefName'] ?? null
            ],
            $result
        );
    }

    /**
     * Github won't return any commits if the ref doesn't exist in the repository anymore.
     *
     * Generally this would be if a branch (ref) was merged into another branch (the base
     * ref), so to account for that, a simple lookup is done to see if the ref exists.
     */
    private function doesRefExist(
        string $owner,
        string $repository,
        string $ref
    ): bool {
        /** @var Refs $result */
        $result = $this->githubClient->graphql()
            ->execute(
                <<<GQL
                {
                    repository(owner: "{$owner}", name: "{$repository}") {
                        refs(refPrefix: "refs/heads/", query: "{$ref}", last: 10) {
                            nodes {
                                name
                            }
                        }
                    }
                }
                GQL
            );

        $refs = ($result['data']['repository']['refs']['nodes'] ?? []);

        foreach ($refs as $ref) {
            if ($ref['name'] === $ref) {
                // The ref has been found, so we can assume it exists and will
                // contain the commits we're looking for
                return true;
            }
        }

        // The ref doesnt exist anymore (i.e. deleted), meaning we wont be able to do a
        // lookup on it directly
        return false;
    }

    /**
     * The cursor GitHub's GraphQL API uses follows the pattern of:
     *
     * ```<starting commit SHA> <offset with a minimum of the number of proceeding commits>```
     *
     * (i.e. 3a6d549ba8bba3987d04fa6ae7b861e8e054968e8 100, or a6d549ba8bba3987d04fa6ae7b861e8e054968e8 200)
     *
     * This method makes a compatible cursor which allows us to paginate
     * through the API, fetching all of the preceding commits up the tree with
     * a predictable response.
     */
    private function makeCursor(string $lastCommit, int $offset): string
    {
        return sprintf(
            '%s %s',
            $lastCommit,
            $offset
        );
    }

    public static function getProvider(): string
    {
        return Provider::GITHUB->value;
    }
}
