<?php

namespace App\Tests\Service\Formatter;

use App\Service\Formatter\PullRequestCommentFormatterService;
use DateTimeImmutable;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Event\Upload;
use Packages\Models\Model\PublishableMessage\PublishablePullRequestMessage;
use Packages\Models\Model\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PullRequestCommentFormatterServiceTest extends TestCase
{
    #[DataProvider('markdownDataProvider')]
    public function testFormat(Upload $upload, PublishablePullRequestMessage $message, string $markdownPath): void
    {
        $formatter = new PullRequestCommentFormatterService();

        $markdown = $formatter->format($upload, $message);

        $this->assertEquals(file_get_contents($markdownPath), $markdown);
    }

    public static function markdownDataProvider(): array
    {
        $upload = new Upload(
            'mock-upload-id',
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-commit',
            [],
            'main',
            'project-root',
            12,
            new Tag('mock-tag', 'mock-commit'),
            new DateTimeImmutable('2023-09-02T10:12:00+00:00'),
        );

        return [
            [
                $upload,
                new PublishablePullRequestMessage(
                    $upload,
                    100.0,
                    100.0,
                    2,
                    0,
                    [
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
                    [
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
                    new DateTimeImmutable()
                ),
                __DIR__ . '/../../Fixture/PullRequestComment/file-and-tag.txt'
            ],
            [
                $upload,
                new PublishablePullRequestMessage(
                    $upload,
                    100.0,
                    100.0,
                    1,
                    1,
                    [
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
                    [
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
                    new DateTimeImmutable()
                ),
                __DIR__ . '/../../Fixture/PullRequestComment/file-and-tag-pending-uploads.txt'
            ]
        ];
    }
}
