<?php

namespace App\Tests\Service\History\Github;

use App\Service\History\Github\GithubCommitHistoryService;
use Github\Api\GraphQL;
use Packages\Clients\Client\Github\GithubAppInstallationClient;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Event\Upload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GithubCommitHistoryServiceTest extends TestCase
{
    public function testGetProvider(): void
    {
        $service = new GithubCommitHistoryService($this->createMock(GithubAppInstallationClient::class));

        $this->assertEquals(Provider::GITHUB->value, $service->getProvider());
    }

    #[DataProvider('commitDataProvider')]
    public function testGetPrecedingCommits(array $responseEdges, array $expectedCommits): void
    {
        $githubClient = $this->createMock(GithubAppInstallationClient::class);
        $gqlClient = $this->createMock(GraphQL::class);

        $mockUpload = $this->createMock(Upload::class);
        $mockUpload->method('getCommit')
            ->willReturn('uploaded-commit');

        $githubClient->method('graphql')
            ->willReturn($gqlClient);

        $gqlClient->expects($this->exactly(count($responseEdges)))
            ->method('execute')
            ->willReturnOnConsecutiveCalls(
                ...array_map(
                    fn(array $edges) => [
                        'data' => [
                            'repository' => [
                                'ref' => [
                                    'target' => [
                                        'history' => [
                                            'edges' => $edges
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    $responseEdges
                )
            );

        $service = new GithubCommitHistoryService($githubClient);

        $this->assertEquals($expectedCommits, $service->getPrecedingCommits($mockUpload));
    }

    public static function commitDataProvider(): array
    {
        return [
            'No commits' => [
                [
                    []
                ],
                []
            ],
            'Single page of commits' => [
                [
                    [
                        [
                            'node' => [
                                'oid' => 'uploaded-commit'
                            ]
                        ],
                        [
                            'node' => [
                                'oid' => '1234567890'
                            ]
                        ],
                        [
                            'node' => [
                                'oid' => '0987654321'
                            ]
                        ]
                    ]
                ],
                [
                    '1234567890',
                    '0987654321'
                ]
            ],
            'Multiple pages of commits' => [
                [
                    [
                        [
                            'node' => [
                                'oid' => 'uploaded-commit'
                            ]
                        ],
                        ...array_fill(
                            0,
                            98,
                            [
                                'node' => [
                                    'oid' => '1234567890'
                                ]
                            ]
                        ),
                        [
                            'node' => [
                                'oid' => '999'
                            ]
                        ]
                    ],
                    [
                        [
                            'node' => [
                                'oid' => '999'
                            ]
                        ],
                        ...array_fill(
                            0,
                            99,
                            [
                                'node' => [
                                    'oid' => '0987654321'
                                ]
                            ]
                        ),
                    ],
                    [
                        [
                            'node' => [
                                'oid' => '45678'
                            ]
                        ],
                        [
                            'node' => [
                                'oid' => '45678'
                            ]
                        ]
                    ]
                ],
                [
                    ...array_fill(0, 98, '1234567890'),
                    '999',
                    ...array_fill(0, 99, '0987654321'),
                    ...array_fill(0, 2, '45678')
                ]
            ],
            'Partial page of commits' => [
                [
                    [
                        [
                            'node' => [
                                'oid' => 'uploaded-commit'
                            ]
                        ],
                        ...array_fill(
                            0,
                            98,
                            [
                                'node' => [
                                    'oid' => '1234567890'
                                ]
                            ]
                        ),
                        [
                            'node' => [
                                'oid' => '999'
                            ]
                        ]
                    ],
                    [
                        [
                            'node' => [
                                'oid' => '999'
                            ]
                        ],
                        ...array_fill(
                            0,
                            30,
                            [
                                'node' => [
                                    'oid' => '0987654321'
                                ]
                            ]
                        ),
                    ]
                ],
                [
                    ...array_fill(0, 98, '1234567890'),
                    '999',
                    ...array_fill(0, 30, '0987654321')
                ]
            ]
        ];
    }
}
