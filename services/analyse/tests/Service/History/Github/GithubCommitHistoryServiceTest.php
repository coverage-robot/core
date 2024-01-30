<?php

namespace App\Tests\Service\History\Github;

use App\Service\History\Github\GithubCommitHistoryService;
use Github\Api\GraphQL;
use Packages\Clients\Client\Github\GithubAppInstallationClientInterface;
use Packages\Contracts\Provider\Provider;
use Packages\Contracts\Tag\Tag;
use Packages\Event\Model\Upload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class GithubCommitHistoryServiceTest extends TestCase
{
    public function testGetProvider(): void
    {
        $service = new GithubCommitHistoryService(
            $this->createMock(GithubAppInstallationClientInterface::class),
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
        $githubClient = $this->createMock(GithubAppInstallationClientInterface::class);
        $gqlClient = $this->createMock(GraphQL::class);

        $mockUpload = new Upload(
            uploadId: 'mock-upload-id',
            provider: Provider::GITHUB,
            owner: 'mock-owner',
            repository: 'mock-repository',
            commit: 'uploaded-commit',
            parent: [],
            ref: 'mock-ref',
            projectRoot: 'mock-project-root',
            tag: new Tag(
                name: 'mock-tag',
                commit: 'mock-tag-commit'
            )
        );

        $githubClient->method('graphql')
            ->willReturn($gqlClient);

        $gqlClient->expects($this->once())
            ->method('execute')
            ->with(
                self::callback(function (string $query) use ($expectedOffset): bool {
                    $this->assertEquals(
                        <<<GQL
                        {
                            repository(owner: "mock-owner", name: "mock-repository") {
                                object(oid: "uploaded-commit") {
                                    ... on Commit {
                                        history(
                                            before: "uploaded-commit {$expectedOffset}",
                                            last: 100
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
                        GQL,
                        $query
                    );
                    return true;
                })
            )
            ->willReturn([
                'data' => [
                    'repository' => [
                        'object' => [
                            'history' => [
                                'nodes' => $response
                            ]
                        ]
                    ]
                ]
            ]);

        $service = new GithubCommitHistoryService($githubClient, new NullLogger());

        $this->assertEquals(
            $expectedCommits,
            $service->getPrecedingCommits($mockUpload, $page)
        );
    }

    public static function commitDataProvider(): array
    {
        return [
            'First page' => [
                1,
                [
                    ...array_fill(
                        0,
                        50,
                        [
                            'oid' => '11111111',
                            'associatedPullRequests' => [
                                'nodes' => [
                                    [
                                        'merged' => false,
                                        'headRefName' => 'mock-ref-2'
                                    ]
                                ]
                            ]
                        ]
                    ),
                    ...array_fill(
                        0,
                        50,
                        [
                            'oid' => '11111111',
                            'associatedPullRequests' => [
                                'nodes' => [
                                    [
                                        'merged' => true,
                                        'headRefName' => 'mock-ref'
                                    ]
                                ]
                            ]
                        ]
                    )
                ],
                101,
                [
                    ...array_fill(
                        0,
                        50,
                        [
                            'commit' => '11111111',
                            'merged' => false,
                            'ref' => 'mock-ref-2'
                        ]
                    ),
                    ...array_fill(
                        0,
                        50,
                        [
                            'commit' => '11111111',
                            'merged' => true,
                            'ref' => 'mock-ref'
                        ]
                    )
                ],
            ],
            'Second page' => [
                2,
                [
                    ...array_fill(
                        0,
                        50,
                        [
                            'oid' => '222222222',
                            'associatedPullRequests' => [
                                'nodes' => [
                                    [
                                        'merged' => true,
                                        'headRefName' => 'mock-ref'
                                    ]
                                ]
                            ]
                        ]
                    ),
                    ...array_fill(
                        0,
                        50,
                        [
                            'oid' => '222222222',
                            'associatedPullRequests' => [
                                'nodes' => []
                            ]
                        ]
                    )
                ],
                201,
                [
                    ...array_fill(
                        0,
                        50,
                        [
                            'commit' => '222222222',
                            'ref' => 'mock-ref',
                            'merged' => true
                        ]
                    ),
                    ...array_fill(
                        0,
                        50,
                        [
                            'commit' => '222222222',
                            'ref' => null,
                            'merged' => true
                        ]
                    )
                ]
            ],
            'Tenth page' => [
                10,
                [
                    ...array_fill(
                        0,
                        50,
                        [
                            'oid' => '9999999999',
                            'associatedPullRequests' => [
                                'nodes' => []
                            ]
                        ]
                    )
                ],
                1001,
                [
                    ...array_fill(
                        0,
                        50,
                        [
                            'commit' => '9999999999',
                            'ref' => null,
                            'merged' => true
                        ]
                    )
                ]
            ]
        ];
    }
}
