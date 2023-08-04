<?php

namespace App\Service\History\Github;

use App\Client\Github\GithubAppInstallationClient;
use App\Service\History\CommitHistoryServiceInterface;
use App\Service\ProviderAwareInterface;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Upload;

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
    private const MAX_COMMITS = 30;

    public function __construct(private readonly GithubAppInstallationClient $githubClient)
    {
    }

    /**
     * @inheritDoc
     */
    public function getPrecedingCommits(Upload $upload): array
    {
        // The first commit returned from the API will be the one associated with the
        // upload, so it needs to be offset by 1 to match the maximum number of commits
        $maxCommits = self::MAX_COMMITS + 1;

        $this->githubClient->authenticateAsRepositoryOwner($upload->getOwner());

        /**
         * @var Result $result
         */
        $result = $this->githubClient->graphql()
            ->execute(
                <<<GQL
                {
                  repository(owner: "{$upload->getOwner()}", name: "{$upload->getRepository()}") {
                    ref(qualifiedName: "{$upload->getRef()}") {
                      name
                      target {
                        ... on Commit {
                          history(before: "{$upload->getCommit()}", first: {$maxCommits}) {
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
            static function (array $commit) use ($upload): ?string {
                if (
                    isset($commit['node']['oid']) &&
                    $commit['node']['oid'] !== $upload->getCommit()
                ) {
                    /**
                     * The Github API will return the current commit (the one associated with
                     * this upload), meaning we want to filter that out of the results, so that the
                     * returned commits are **only** those which preceded the upload
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
