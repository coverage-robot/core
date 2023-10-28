<?php

namespace App\Tests\Service\History\Github;

use App\Service\History\Github\GithubCommitHistoryService;
use Github\Api\GraphQL;
use Packages\Clients\Client\Github\GithubAppInstallationClient;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Event\Upload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class GithubCommitHistoryServiceTest extends TestCase
{
    public function testGetProvider(): void
    {
        $service = new GithubCommitHistoryService(
            $this->createMock(GithubAppInstallationClient::class),
            new NullLogger()
        );

        $this->assertEquals(Provider::GITHUB->value, $service->getProvider());
    }

    #[DataProvider('commitDataProvider')]
    public function testGetPrecedingCommits(
        int $page,
        array $response,
        int $expectedOffset,
        array $expectedCommits
    ): void {
        $githubClient = $this->createMock(GithubAppInstallationClient::class);
        $gqlClient = $this->createMock(GraphQL::class);

        $mockUpload = $this->createMock(Upload::class);
        $mockUpload->method('getOwner')
            ->willReturn('mock-owner');
        $mockUpload->method('getRepository')
            ->willReturn('mock-repository');
        $mockUpload->method('getRef')
            ->willReturn('mock-ref');
        $mockUpload->method('getCommit')
            ->willReturn('uploaded-commit');

        $githubClient->method('graphql')
            ->willReturn($gqlClient);

        $gqlClient->expects($this->once())
            ->method('execute')
            ->with(
                self::callback(function (string $query) use ($expectedOffset) {
                    $this->assertEquals(
                        <<<GQL
                        {
                          repository(owner: "mock-owner", name: "mock-repository") {
                            ref(qualifiedName: "mock-ref") {
                              name
                              target {
                                ... on Commit {
                                  history(
                                    before: "uploaded-commit {$expectedOffset}",
                                    last: 100
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
                        GQL,
                        $query
                    );
                    return true;
                })
            )
            ->willReturn([
                'data' => [
                    'repository' => [
                        'ref' => [
                            'target' => [
                                'history' => [
                                    'nodes' => $response
                                ]
                            ]
                        ]
                    ]
                ]
            ]);

        $service = new GithubCommitHistoryService($githubClient, new NullLogger());

        $this->assertEquals($expectedCommits, $service->getPrecedingCommits($mockUpload, $page));
    }

    public static function commitDataProvider(): array
    {
        return [
            'First page' => [
                1,
                array_fill(
                    0,
                    100,
                    [
                        'oid' => '11111111'
                    ]
                ),
                101,
                array_fill(
                    0,
                    100,
                    '11111111'
                )
            ],
            'Second page' => [
                2,
                array_fill(
                    0,
                    100,
                    [
                        'oid' => '222222222'
                    ]
                ),
                201,
                array_fill(
                    0,
                    100,
                    '222222222'
                )
            ],
            'Tenth page' => [
                10,
                array_fill(
                    0,
                    100,
                    [
                        'oid' => '9999999999'
                    ]
                ),
                1001,
                array_fill(
                    0,
                    100,
                    '9999999999'
                )
            ]
        ];
    }
}
