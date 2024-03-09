<?php

namespace App\Service\History\Github;

use App\Exception\CommitHistoryException;
use App\Model\ReportWaypoint;
use App\Service\History\CommitHistoryService;
use App\Service\History\CommitHistoryServiceInterface;
use Github\Exception\ExceptionInterface;
use Override;
use Packages\Clients\Client\Github\GithubAppInstallationClientInterface;
use Packages\Contracts\Event\EventInterface;
use Packages\Contracts\Provider\Provider;
use Packages\Contracts\Provider\ProviderAwareInterface;
use Packages\Telemetry\Service\MetricServiceInterface;
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
        private readonly GithubAppInstallationClientInterface $githubClient,
        private readonly LoggerInterface $githubHistoryLogger,
        private readonly MetricServiceInterface $metricService
    ) {
    }

    /**
     * @inheritDoc
     *
     * @return array{commit: string, merged: bool, ref: string|null}[]
     * @throws CommitHistoryException
     */
    #[Override]
    public function getPrecedingCommits(EventInterface|ReportWaypoint $waypoint, int $page = 1): array
    {
        $offset = (max(1, $page) * CommitHistoryService::COMMITS_TO_RETURN_PER_PAGE) + 1;

        $this->metricService->increment(
            metric: 'CommitHistoryRetrievalRequest',
            dimensions: [['provider', 'owner']],
            properties: ['provider' => Provider::GITHUB->value, 'owner' => $waypoint->getOwner()]
        );

        try {
            $commits = $this->getHistoricCommits(
                $waypoint->getOwner(),
                $waypoint->getRepository(),
                $waypoint->getCommit(),
                $offset
            );
        } catch (ExceptionInterface $exception) {
            throw new CommitHistoryException(
                sprintf(
                    'Failed to retrieve commit history for %s',
                    (string)$waypoint
                ),
                previous: $exception
            );
        }

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
        $this->githubClient->authenticateAsRepositoryOwner($owner);

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
