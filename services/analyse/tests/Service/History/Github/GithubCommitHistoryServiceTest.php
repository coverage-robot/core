<?php

namespace App\Tests\Service\History\Github;

use App\Client\Github\GithubAppInstallationClient;
use App\Service\History\Github\GithubCommitHistoryService;
use Github\Api\GraphQL;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Upload;
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
    public function testGetPrecedingCommits(array $response, array $expectedCommits): void
    {
        $githubClient = $this->createMock(GithubAppInstallationClient::class);
        $gqlClient = $this->createMock(GraphQL::class);

        $githubClient->expects($this->once())
            ->method('graphql')
            ->willReturn($gqlClient);

        $gqlClient->expects($this->once())
            ->method('execute')
            ->willReturn($response);

        $service = new GithubCommitHistoryService($githubClient);

        $this->assertEquals($expectedCommits, $service->getPrecedingCommits($this->createMock(Upload::class)));
    }

    public static function commitDataProvider(): array
    {
        return [
            'No commits' => [
                [
                    'data' => [
                        'repository' => [
                            'ref' => [
                                'target' => [
                                    'history' => [
                                        'edges' => []
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                []
            ],
            'Multiple commits' => [
                [
                    'data' => [
                        'repository' => [
                            'ref' => [
                                'target' => [
                                    'history' => [
                                        'edges' => [
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
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    '1234567890',
                    '0987654321'
                ]
            ]
        ];
    }
}
