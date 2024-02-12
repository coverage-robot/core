<?php

namespace App\Tests\Service\Templating;

use App\Enum\TemplateVariant;
use App\Service\Templating\TemplateRenderingService;
use DateTimeImmutable;
use Packages\Contracts\Provider\Provider;
use Packages\Contracts\PublishableMessage\PublishableMessageInterface;
use Packages\Contracts\Tag\Tag;
use Packages\Event\Model\Upload;
use Packages\Event\Model\UploadsFinalised;
use Packages\Message\PublishableMessage\PublishableCheckRunMessage;
use Packages\Message\PublishableMessage\PublishableCheckRunStatus;
use Packages\Message\PublishableMessage\PublishableMissingCoverageAnnotationMessage;
use Packages\Message\PublishableMessage\PublishablePartialBranchAnnotationMessage;
use Packages\Message\PublishableMessage\PublishablePullRequestMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TemplateRenderingServiceTest extends KernelTestCase
{
    use MatchesSnapshots;

    #[DataProvider('messageDataProvider')]
    public function testRendering(
        PublishableMessageInterface $message,
        TemplateVariant $variant
    ): void {
        /**
         * @var TemplateRenderingService $templateRenderingService
         */
        $templateRenderingService = $this->getContainer()
            ->get(TemplateRenderingService::class);

        $markdown = $templateRenderingService->render(
            $message,
            $variant
        );

        $this->assertMatchesSnapshot($markdown);
    }

    public static function messageDataProvider(): array
    {
        $event = new Upload(
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

        return [
            [
                new PublishablePullRequestMessage(
                    event: $event,
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
                ),
                TemplateVariant::COMPLETE
            ],
            [
                new PublishablePullRequestMessage(
                    event: $event,
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
                ),
                TemplateVariant::COMPLETE
            ],
            [
                new PublishablePullRequestMessage(
                    event: $event,
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
                ),
                TemplateVariant::COMPLETE
            ],
            [
                new PublishableCheckRunMessage(
                    event: $event,
                    status: PublishableCheckRunStatus::IN_PROGRESS,
                    coveragePercentage: 0,
                    baseCommit: 'mock-base-commit',
                    coverageChange: null
                ),
                TemplateVariant::IN_PROGRESS
            ],
            [
                new PublishableCheckRunMessage(
                    event: new UploadsFinalised(
                        provider: Provider::GITHUB,
                        owner: 'mock-owner',
                        repository: 'mock-repository',
                        ref: 'main',
                        commit: 'mock-commit',
                        parent: [],
                    ),
                    status: PublishableCheckRunStatus::SUCCESS,
                    coveragePercentage: 0,
                    baseCommit: 'mock-base-commit',
                    coverageChange: null
                ),
                TemplateVariant::COMPLETE
            ],
            [
                new PublishableCheckRunMessage(
                    event: $event,
                    status: PublishableCheckRunStatus::SUCCESS,
                    coveragePercentage: 83.2,
                    baseCommit: 'mock-base-commit',
                    coverageChange: 0.2
                ),
                TemplateVariant::COMPLETE
            ],
            [
                new PublishableCheckRunMessage(
                    event: $event,
                    status: PublishableCheckRunStatus::SUCCESS,
                    coveragePercentage: 83.2,
                    baseCommit: 'mock-base-commit',
                    coverageChange: 0
                ),
                TemplateVariant::COMPLETE
            ],
            [
                new PublishableCheckRunMessage(
                    event: $event,
                    status: PublishableCheckRunStatus::SUCCESS,
                    coveragePercentage: 83.2,
                    baseCommit: 'mock-base-commit',
                    coverageChange: -2
                ),
                TemplateVariant::COMPLETE
            ],
            [
                new PublishableMissingCoverageAnnotationMessage(
                    event: $event,
                    fileName: 'mock-file',
                    startingOnMethod: false,
                    startLineNumber: 1,
                    endLineNumber: 10,
                    validUntil: $event->getEventTime()
                ),
                TemplateVariant::COMPLETE,
                'Opportunity For New Coverage',
                'The next 9 lines are not covered by any tests.'
            ],
            [
                new PublishableMissingCoverageAnnotationMessage(
                    event: $event,
                    fileName: 'mock-file',
                    startingOnMethod: false,
                    startLineNumber: 1,
                    endLineNumber: 1,
                    validUntil: $event->getEventTime()
                ),
                TemplateVariant::COMPLETE,
                'Opportunity For New Coverage',
                'This line is not covered by any tests.'
            ],
            [
                new PublishableMissingCoverageAnnotationMessage(
                    event: $event,
                    fileName: 'mock-file',
                    startingOnMethod: false,
                    startLineNumber: 1,
                    endLineNumber: 2,
                    validUntil: $event->getEventTime()
                ),
                TemplateVariant::COMPLETE,
                'Opportunity For New Coverage',
                'The next 1 lines are not covered by any tests.'
            ],
            [
                new PublishablePartialBranchAnnotationMessage(
                    event: $event,
                    fileName: 'mock-file',
                    startLineNumber: 1,
                    endLineNumber: 1,
                    totalBranches: 2,
                    coveredBranches: 1,
                    validUntil: $event->getEventTime()
                ),
                TemplateVariant::COMPLETE,
                'Opportunity For New Coverage',
                '50% of these branches are not covered by any tests.'
            ],
            [
                new PublishablePartialBranchAnnotationMessage(
                    event: $event,
                    fileName: 'mock-file',
                    startLineNumber: 1,
                    endLineNumber: 1,
                    totalBranches: 1,
                    coveredBranches: 0,
                    validUntil: $event->getEventTime()
                ),
                TemplateVariant::COMPLETE,
                'Opportunity For New Coverage',
                'None of these branches are covered by tests.'
            ],
            [
                new PublishablePartialBranchAnnotationMessage(
                    event: $event,
                    fileName: 'mock-file',
                    startLineNumber: 1,
                    endLineNumber: 1,
                    totalBranches: 5,
                    coveredBranches: 2,
                    validUntil: $event->getEventTime()
                ),
                TemplateVariant::COMPLETE,
                'Opportunity For New Coverage',
                '60% of these branches are not covered by any tests.'
            ],
            [
                new PublishableMissingCoverageAnnotationMessage(
                    event: $event,
                    fileName: 'mock-file',
                    startingOnMethod: true,
                    startLineNumber: 1,
                    endLineNumber: 2,
                    validUntil: $event->getEventTime()
                ),
                TemplateVariant::COMPLETE,
                'Opportunity For New Coverage',
                'This method has not been covered by any tests.'
            ],
            [
                new PublishableMissingCoverageAnnotationMessage(
                    event: $event,
                    fileName: 'mock-file',
                    startingOnMethod: false,
                    startLineNumber: 1,
                    endLineNumber: 100,
                    validUntil: $event->getEventTime()
                ),
                TemplateVariant::COMPLETE,
                'Opportunity For New Coverage',
                'The next 99 lines are not covered by any tests.'
            ],
        ];
    }
}
