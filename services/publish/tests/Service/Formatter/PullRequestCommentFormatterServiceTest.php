<?php

namespace App\Tests\Service\Formatter;

use App\Service\Formatter\PullRequestCommentFormatterService;
use DateTimeImmutable;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Model\Upload;
use Packages\Message\PublishableMessage\PublishablePullRequestMessage;
use Packages\Models\Model\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Spatie\Snapshots\MatchesSnapshots;

class PullRequestCommentFormatterServiceTest extends TestCase
{
    use MatchesSnapshots;

    #[DataProvider('markdownDataProvider')]
    public function testFormat(Upload $upload, PublishablePullRequestMessage $message): void
    {
        $formatter = new PullRequestCommentFormatterService();

        $markdown = $formatter->format($upload, $message);

        $this->assertMatchesSnapshot($markdown);
    }

    public static function markdownDataProvider(): array
    {
        $pullRequestUpload = new Upload(
            uploadId: 'mock-upload-id',
            provider: Provider::GITHUB,
            owner: 'mock-owner',
            repository: 'mock-repository',
            commit: 'mock-commit',
            parent: [],
            ref: 'main',
            projectRoot: 'project-root',
            tag: new Tag('mock-tag', 'mock-commit'),
            pullRequest: 12,
            baseCommit: 'commit-on-main',
            baseRef: 'main',
            eventTime: new DateTimeImmutable('2023-09-02T10:12:00+00:00'),
        );

        $missingPullRequestUpload = new Upload(
            uploadId: 'mock-upload-id',
            provider: Provider::GITHUB,
            owner: 'mock-owner',
            repository: 'mock-repository',
            commit: 'mock-commit',
            parent: [],
            ref: 'main',
            projectRoot: 'project-root',
            tag: new Tag('mock-tag', 'mock-commit'),
            eventTime: new DateTimeImmutable('2023-09-02T10:12:00+00:00'),
        );

        return [
            [
                $pullRequestUpload,
                new PublishablePullRequestMessage(
                    event: $pullRequestUpload,
                    coveragePercentage: 100.0,
                    diffCoveragePercentage: 100.0,
                    successfulUploads: 2,
                    tagCoverage: [
                        [
                            'tag' => [
                                'name' => 'mock-tag',
                                'commit' => 'mock-commit',
                            ],
                            'lines' => 100,
                            'covered' => 48,
                            'partial' => 2,
                            'uncovered' => 50,
                            'coveragePercentage' => 50,
                        ],
                        [
                            'tag' => [
                                'name' => 'mock-tag-2',
                                'commit' => 'mock-commit-2',
                            ],
                            'lines' => 2,
                            'covered' => 0,
                            'partial' => 0,
                            'uncovered' => 2,
                            'coveragePercentage' => 0,
                        ]
                    ],
                    leastCoveredDiffFiles: [
                        [
                            'fileName' => 'mock-file',
                            'lines' => 100,
                            'covered' => 48,
                            'partial' => 2,
                            'uncovered' => 50,
                            'coveragePercentage' => 50,
                        ],
                        [
                            'fileName' => 'mock-file-2',
                            'lines' => 100,
                            'covered' => 100,
                            'partial' => 0,
                            'uncovered' => 0,
                            'coveragePercentage' => 100.0,
                        ]
                    ],
                    baseCommit: 'mock-base-commit',
                    coverageChange: 0.1,
                )
            ],
            [
                $pullRequestUpload,
                new PublishablePullRequestMessage(
                    event: $pullRequestUpload,
                    coveragePercentage: 100.0,
                    diffCoveragePercentage: 100.0,
                    successfulUploads: 2,
                    tagCoverage: [
                        [
                            'tag' => [
                                'name' => 'mock-tag',
                                'commit' => 'mock-commit',
                            ],
                            'lines' => 100,
                            'covered' => 48,
                            'partial' => 2,
                            'uncovered' => 50,
                            'coveragePercentage' => 50,
                        ],
                        [
                            'tag' => [
                                'name' => 'mock-tag-2',
                                'commit' => 'mock-commit-2',
                            ],
                            'lines' => 2,
                            'covered' => 0,
                            'partial' => 0,
                            'uncovered' => 2,
                            'coveragePercentage' => 0,
                        ]
                    ],
                    leastCoveredDiffFiles: [
                        [
                            'fileName' => 'mock-file',
                            'lines' => 100,
                            'covered' => 48,
                            'partial' => 2,
                            'uncovered' => 50,
                            'coveragePercentage' => 50,
                        ],
                        [
                            'fileName' => 'mock-file-2',
                            'lines' => 100,
                            'covered' => 100,
                            'partial' => 0,
                            'uncovered' => 0,
                            'coveragePercentage' => 100.0,
                        ]
                    ],
                    baseCommit: 'mock-base-commit',
                    coverageChange: -1.2
                )
            ],
            [
                $pullRequestUpload,
                new PublishablePullRequestMessage(
                    event: $pullRequestUpload,
                    coveragePercentage: 100.0,
                    diffCoveragePercentage: null,
                    successfulUploads: 2,
                    tagCoverage: [
                        [
                            'tag' => [
                                'name' => 'mock-tag',
                                'commit' => 'mock-commit',
                            ],
                            'lines' => 100,
                            'covered' => 48,
                            'partial' => 2,
                            'uncovered' => 50,
                            'coveragePercentage' => 50,
                        ],
                        [
                            'tag' => [
                                'name' => 'mock-tag-2',
                                'commit' => 'mock-commit-2',
                            ],
                            'lines' => 2,
                            'covered' => 0,
                            'partial' => 0,
                            'uncovered' => 2,
                            'coveragePercentage' => 0,
                        ]
                    ],
                    leastCoveredDiffFiles: [],
                    baseCommit: 'mock-base-commit',
                    coverageChange: 0
                )
            ],
            [
                // This would be some form of failure state, where we're somehow trying to publish a PR comment
                // when the upload wasn't attached to any PRs
                $missingPullRequestUpload,
                new PublishablePullRequestMessage(
                    event: $missingPullRequestUpload,
                    coveragePercentage: 100.0,
                    diffCoveragePercentage: null,
                    successfulUploads: 2,
                    tagCoverage: [
                        [
                            'tag' => [
                                'name' => 'mock-tag',
                                'commit' => 'mock-commit',
                            ],
                            'lines' => 100,
                            'covered' => 48,
                            'partial' => 2,
                            'uncovered' => 50,
                            'coveragePercentage' => 50,
                        ],
                        [
                            'tag' => [
                                'name' => 'mock-tag-2',
                                'commit' => 'mock-commit-2',
                            ],
                            'lines' => 2,
                            'covered' => 0,
                            'partial' => 0,
                            'uncovered' => 2,
                            'coveragePercentage' => 0,
                        ]
                    ],
                    leastCoveredDiffFiles: [],
                    baseCommit: 'mock-base-commit',
                    coverageChange: 0
                )
            ]
        ];
    }
}
