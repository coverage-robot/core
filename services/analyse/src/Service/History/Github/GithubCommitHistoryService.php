<?php

namespace App\Service\History\Github;

use App\Service\History\CommitHistoryService;
use App\Service\History\CommitHistoryServiceInterface;
use App\Service\ProviderAwareInterface;
use Packages\Clients\Client\Github\GithubAppInstallationClient;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Event\EventInterface;
use Psr\Log\LoggerInterface;

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
    public function __construct(
        private readonly GithubAppInstallationClient $githubClient,
        private readonly LoggerInterface $githubHistoryLogger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getPrecedingCommits(EventInterface $event, int $page = 1): array
    {
        $this->githubClient->authenticateAsRepositoryOwner($event->getOwner());

        $offset = (max(1, $page) * CommitHistoryService::COMMITS_TO_RETURN_PER_PAGE) + 1;

        /** @var string[] $commits */
        $commits = [];

        $commits = [
            ...$commits,
            ...$this->getHistoricCommits(
                $event->getOwner(),
                $event->getRepository(),
                $event->getRef(),
                $event->getCommit(),
                $offset
            )
        ];

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

    /**
     * @return string[]
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
            static fn(array $commit): string => $commit['oid'],
            $result
        );
    }

    public static function getProvider(): string
    {
        return Provider::GITHUB->value;
    }
}
