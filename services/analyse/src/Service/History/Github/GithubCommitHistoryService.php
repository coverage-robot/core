<?php

namespace App\Service\History\Github;

use App\Client\Github\GithubAppInstallationClient;
use App\Service\History\CommitHistoryServiceInterface;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Upload;

class GithubCommitHistoryService implements CommitHistoryServiceInterface
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
        $maxCommits = self::MAX_COMMITS;

        $this->githubClient->authenticateAsRepositoryOwner($upload->getOwner());

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

        $commits = array_map(static fn(array $commit) => $commit["node"]["oid"],$result['data']['repository']['ref']['target']['history']['edges']);

        return $commits;
    }

    public static function getProvider(): string
    {
        return Provider::GITHUB->value;
    }
}
