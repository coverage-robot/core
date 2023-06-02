<?php

namespace App\Tests\Service\Formatter;

use App\Enum\ProviderEnum;
use App\Model\QueryResult\TotalTagCoverageQueryResult;
use App\Model\Upload;
use App\Service\Formatter\PullRequestCommentFormatterService;
use App\Tests\Mock\Factory\MockPublishableCoverageDataFactory;
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

        $this->assertStringContainsString(
            sprintf(
                'This is for %s commit. Which has had %s uploads.',
                $upload->getCommit(),
                $publishableCoverageData->getTotalUploads()
            ),
            $markdown
        );

        $this->assertStringContainsString(
            sprintf(
                'Total coverage is: **%s%%**',
                $publishableCoverageData->getCoveragePercentage()
            ),
            $markdown
        );

        $this->assertStringContainsString(
            sprintf(
                'Consisting of *%s* covered lines, out of *%s* total lines.',
                $publishableCoverageData->getAtLeastPartiallyCoveredLines(),
                $publishableCoverageData->getTotalLines()
            ),
            $markdown
        );

        foreach ($publishableCoverageData->getTagCoverage()->getTags() as $tag) {
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
                    'getTagCoverage' => TotalTagCoverageQueryResult::from([
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
                new Upload([
                    'uploadId' => 'mock-upload',
                    'provider' => ProviderEnum::GITHUB->value,
                    'owner' => 'mock-owner',
                    'repository' => 'mock-repository',
                    'commit' => 'mock-commit',
                    'parent' => 'mock-parent',
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
                    'getTagCoverage' => TotalTagCoverageQueryResult::from([
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
                new Upload([
                    'uploadId' => 'mock-upload',
                    'provider' => ProviderEnum::GITHUB->value,
                    'owner' => 'mock-owner',
                    'repository' => 'mock-repository',
                    'commit' => 'mock-commit-2',
                    'parent' => 'mock-parent',
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
                    'getTagCoverage' => TotalTagCoverageQueryResult::from([]),
                ],
                new Upload([
                    'uploadId' => 'mock-upload',
                    'provider' => ProviderEnum::GITHUB->value,
                    'owner' => 'mock-owner',
                    'repository' => 'mock-repository',
                    'commit' => 'mock-commit-3',
                    'parent' => 'mock-parent',
                    'pullRequest' => 1,
                ])
            ]
        ];
    }
}
