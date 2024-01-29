<?php

namespace App\Service\History\Github;

use App\Model\ReportWaypoint;
use App\Service\History\CommitHistoryService;
use App\Service\History\CommitHistoryServiceInterface;
use App\Service\ProviderAwareInterface;
use Override;
use Packages\Clients\Client\Github\GithubAppInstallationClient;
use Packages\Contracts\Event\EventInterface;
use Packages\Contracts\Provider\Provider;
use Psr\Log\LoggerInterface;

/**
 * @psalm-type History = array{
 *     data: array{
 *          repository: array{
*                  object: array{
*                       history: array{
*                           nodes: array{
*                               oid: string,
*                               associatedPullRequests: array{
*                                   nodes: array{
*                                       merged: bool,
*                                       headRefName: string
*                                   }[]
*                               }
*                           }[]
*                       }
*                   }
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
final class GithubCommitHistoryService implements CommitHistoryServiceInterface, ProviderAwareInterface
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
    #[Override]
    public function getPrecedingCommits(EventInterface|ReportWaypoint $waypoint, int $page = 1): array
    {
        $this->githubClient->authenticateAsRepositoryOwner($waypoint->getOwner());

        $offset = (max(1, $page) * CommitHistoryService::COMMITS_TO_RETURN_PER_PAGE) + 1;

        $commits = $this->getHistoricCommits(
            $waypoint->getOwner(),
            $waypoint->getRepository(),
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
                        object(oid: "{$beforeCommit}") {
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
                GQL
            );

        $result = $result['data']['repository']['object']['history']['nodes'] ?? [];

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

    #[Override]
    public static function getProvider(): string
    {
        return Provider::GITHUB->value;
    }
}
