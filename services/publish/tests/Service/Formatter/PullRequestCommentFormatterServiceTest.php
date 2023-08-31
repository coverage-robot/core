<?php

namespace App\Tests\Service\Formatter;

use App\Service\Formatter\PullRequestCommentFormatterService;
use DateTimeImmutable;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\PublishableMessage\PublishablePullRequestMessage;
use Packages\Models\Model\Tag;
use Packages\Models\Model\Upload;
use PHPUnit\Framework\TestCase;

class PullRequestCommentFormatterServiceTest extends TestCase
{
    public function testFormat(): void
    {
        $upload = new Upload(
            'mock-upload-id',
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-commit',
            [],
            'master',
            12,
            new Tag('mock-tag', 'mock-commit'),
        );
        $message = new PublishablePullRequestMessage(
            $upload,
            100.0,
            100.0,
            2,
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
        );

        $formatter = new PullRequestCommentFormatterService();

        $markdown = $formatter->format($upload, $message);

        $files = $message->getLeastCoveredDiffFiles();

        $tags = $message->getTagCoverage();

        $this->assertStringContainsString(
            sprintf(
                '> Merging #%s, with **%s** uploaded coverage files on %s',
                $upload->getPullRequest(),
                $message->getTotalUploads(),
                $upload->getCommit(),
            ),
            $markdown
        );

        $this->assertStringContainsString(
            sprintf(
                '| %s%% | %s%% |',
                $message->getCoveragePercentage(),
                $message->getDiffCoveragePercentage()
            ),
            $markdown
        );

        foreach ($files as $file) {
            $this->assertStringContainsString(
                sprintf(
                    '| %s | %s%% |',
                    $file['fileName'],
                    $file['coveragePercentage']
                ),
                $markdown
            );
        }

        foreach ($tags as $tag) {
            $this->assertStringContainsString(
                sprintf(
                    '| %s | %s | %s | %s | %s | %s%% |',
                    sprintf(
                        '%s%s',
                        $tag['tag']['name'],
                        $upload->getCommit() !== $tag['tag']['commit'] ?
                            '<br><sub>(Carried forward from ' . $tag['tag']['commit'] . ')</sub>' :
                            ''
                    ),
                    $tag['lines'],
                    $tag['covered'],
                    $tag['partial'],
                    $tag['uncovered'],
                    $tag['coveragePercentage']
                ),
                $markdown
            );
        }
    }
}
