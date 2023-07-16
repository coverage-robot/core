<?php

namespace App\Tests\Service\Formatter;

use App\Query\Result\FileCoverageCollectionQueryResult;
use App\Query\Result\TagCoverageCollectionQueryResult;
use App\Service\Formatter\PullRequestCommentFormatterService;
use App\Tests\Mock\Factory\MockPublishableCoverageDataFactory;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Upload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PullRequestCommentFormatterServiceTest extends TestCase
{
    #[DataProvider('coverageDataProvider')]
    public function testFormat(array $coverageDataMethods, Upload $upload): void
    {
        $publishableCoverageData = MockPublishableCoverageDataFactory::createMock(
            $this,
            $coverageDataMethods
        );

        $formatter = new PullRequestCommentFormatterService();

        $markdown = $formatter->format($upload, $publishableCoverageData);

        $files = $publishableCoverageData
            ->getLeastCoveredDiffFiles(PullRequestCommentFormatterService::MAX_IMPACTED_FILES)
            ->getFiles();

        $tags = $publishableCoverageData->getTagCoverage()
            ->getTags();

        $this->assertStringContainsString(
            sprintf(
                '> Merging #%s, with **%s** uploaded coverage files on %s',
                $upload->getPullRequest(),
                $publishableCoverageData->getTotalUploads(),
                $upload->getCommit(),
            ),
            $markdown
        );

        $this->assertStringContainsString(
            sprintf(
                '| %s%% | %s%% |',
                $publishableCoverageData->getCoveragePercentage(),
                $publishableCoverageData->getDiffCoveragePercentage()
            ),
            $markdown
        );

        foreach ($files as $file) {
            $this->assertStringContainsString(
                sprintf(
                    '| %s | %s%% |',
                    $file->getFileName(),
                    $file->getCoveragePercentage()
                ),
                $markdown
            );
        }

        foreach ($tags as $tag) {
            $this->assertStringContainsString(
                sprintf(
                    '| %s | %s | %s | %s | %s | %s%% |',
                    $tag->getTag(),
                    $tag->getLines(),
                    $tag->getCovered(),
                    $tag->getPartial(),
                    $tag->getUncovered(),
                    $tag->getCoveragePercentage()
                ),
                $markdown
            );
        }
    }

    public static function coverageDataProvider(): array
    {
        return [
            'Single tag' => [
                [
                    'getTotalUploads' => 10,
                    'getTotalLines' => 100,
                    'getAtLeastPartiallyCoveredLines' => 50,
                    'getUncoveredLines' => 50,
                    'getCoveragePercentage' => 50.0,
                    'getDiffCoveragePercentage' => 50.0,
                    'getLeastCoveredDiffFiles' => FileCoverageCollectionQueryResult::from([
                        [
                            'fileName' => 'mock-file',
                            'coveragePercentage' => 50.0,
                            'lines' => 100,
                            'covered' => 50,
                            'partial' => 0,
                            'uncovered' => 50
                        ],
                        [
                            'fileName' => 'mock-file-2',
                            'coveragePercentage' => 0.0,
                            'lines' => 5,
                            'covered' => 0,
                            'partial' => 0,
                            'uncovered' => 5
                        ]
                    ]),
                    'getTagCoverage' => TagCoverageCollectionQueryResult::from([
                        [
                            'tag' => 'mock',
                            'coveragePercentage' => 50.0,
                            'lines' => 100,
                            'covered' => 49,
                            'partial' => 1,
                            'uncovered' => 50
                        ]
                    ]),
                ],
                Upload::from([
                    'uploadId' => 'mock-upload',
                    'provider' => Provider::GITHUB->value,
                    'owner' => 'mock-owner',
                    'repository' => 'mock-repository',
                    'commit' => 'mock-commit',
                    'parent' => '["mock-parent"]',
                    'tag' => 'mock-tag',
                    'ref' => 'mock-ref',
                    'pullRequest' => 1,
                ])
            ],
            'Multiple tags' => [
                [
                    'getTotalUploads' => 10,
                    'getTotalLines' => 100,
                    'getAtLeastPartiallyCoveredLines' => 50,
                    'getUncoveredLines' => 50,
                    'getCoveragePercentage' => 50.0,
                    'getDiffCoveragePercentage' => 50.0,
                    'getLeastCoveredDiffFiles' => FileCoverageCollectionQueryResult::from([
                        [
                            'fileName' => 'mock-file',
                            'coveragePercentage' => 50.0,
                            'lines' => 100,
                            'covered' => 49,
                            'partial' => 1,
                            'uncovered' => 50
                        ],
                        [
                            'fileName' => 'mock-file-2',
                            'coveragePercentage' => 0.0,
                            'lines' => 5,
                            'covered' => 0,
                            'partial' => 0,
                            'uncovered' => 5
                        ]
                    ]),
                    'getTagCoverage' => TagCoverageCollectionQueryResult::from([
                        [
                            'tag' => 'mock-service-1',
                            'coveragePercentage' => 50.5,
                            'lines' => 99,
                            'covered' => 49,
                            'partial' => 1,
                            'uncovered' => 49
                        ],
                        [
                            'tag' => 'mock-service-2',
                            'coveragePercentage' => 0.0,
                            'lines' => 1,
                            'covered' => 0,
                            'partial' => 0,
                            'uncovered' => 1
                        ]
                    ]),
                ],
                Upload::from([
                    'uploadId' => 'mock-upload',
                    'provider' => Provider::GITHUB->value,
                    'owner' => 'mock-owner',
                    'repository' => 'mock-repository',
                    'commit' => 'mock-commit-2',
                    'parent' => '["mock-parent"]',
                    'tag' => 'mock-tag',
                    'ref' => 'mock-ref',
                    'pullRequest' => 1,
                ])
            ],
            'No uploaded tags' => [
                [
                    'getTotalUploads' => 10,
                    'getTotalLines' => 100,
                    'getAtLeastPartiallyCoveredLines' => 50,
                    'getUncoveredLines' => 50,
                    'getCoveragePercentage' => 50.0,
                    'getDiffCoveragePercentage' => 0.0,
                    'getLeastCoveredDiffFiles' => FileCoverageCollectionQueryResult::from([]),
                    'getTagCoverage' => TagCoverageCollectionQueryResult::from([]),
                ],
                Upload::from([
                    'uploadId' => 'mock-upload',
                    'provider' => Provider::GITHUB->value,
                    'owner' => 'mock-owner',
                    'repository' => 'mock-repository',
                    'commit' => 'mock-commit-3',
                    'parent' => '["mock-parent"]',
                    'tag' => 'mock-tag',
                    'ref' => 'mock-ref',
                    'pullRequest' => 1,
                ])
            ]
        ];
    }
}
