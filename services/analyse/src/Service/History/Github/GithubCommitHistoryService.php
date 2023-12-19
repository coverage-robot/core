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
 * @psalm-type Result = array{
 *     data: array{
 *          repository: array{
 *                  ref: array{
 *                      target: array{
 *                          history: array{
 *                              nodes: array{
 *                                  oid: string,
 *                                  associatedPullRequests: array{
 *                                      nodes: array{
 *                                          merged: bool
 *                                      }[]
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
    public function __construct(
        private readonly GithubAppInstallationClient $githubClient,
        private readonly LoggerInterface $githubHistoryLogger
    ) {
    }

    /**
     * @inheritDoc
     *
     * @return array{commit: string, isOnBaseRef: bool}[]
     */
    public function getPrecedingCommits(EventInterface|ReportWaypoint $event, int $page = 1): array
    {
        $this->githubClient->authenticateAsRepositoryOwner($event->getOwner());

        $offset = (max(1, $page) * CommitHistoryService::COMMITS_TO_RETURN_PER_PAGE) + 1;

        $commits = $this->getHistoricCommits(
            $event->getOwner(),
            $event->getRepository(),
            $event->getRef(),
            $event->getCommit(),
            $offset
        );

        $this->githubHistoryLogger->info(
            sprintf(
                'Fetched %s preceding commits from GitHub for %s',
                CommitHistoryService::COMMITS_TO_RETURN_PER_PAGE,
                (string)$event
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
     * @return array{commit: string, isOnBaseRef: bool}[]
     */
    private function getHistoricCommits(
        string $owner,
        string $repository,
        string $ref,
        string $beforeCommit,
        int $offset
    ): array {
        $commitsPerPage = CommitHistoryService::COMMITS_TO_RETURN_PER_PAGE;

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
                          history(
                            before: "{$this->makeCursor($beforeCommit, $offset)}",
                            last: {$commitsPerPage}
                          ) {
                            nodes {
                              oid
                              associatedPullRequests(last: 1) {
                                nodes {
                                  merged
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

                // The commit _must_ be on the base ref (i.e. not in a PR) if theres no unmerged PRs, or there
                // was no PR to begin with (a direct push)
                'isOnBaseRef' => $commit['associatedPullRequests']['nodes'][0]['merged'] ?? true
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

    public static function getProvider(): string
    {
        return Provider::GITHUB->value;
    }
}
