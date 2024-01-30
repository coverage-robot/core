<?php

namespace App\Tests\Service\Formatter;

use App\Service\Formatter\CheckAnnotationFormatterService;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Model\UploadsFinalised;
use Packages\Message\PublishableMessage\PublishableAnnotationInterface;
use Packages\Message\PublishableMessage\PublishableMissingCoverageAnnotationMessage;
use Packages\Message\PublishableMessage\PublishablePartialBranchAnnotationMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CheckAnnotationFormatterServiceTest extends TestCase
{
    #[DataProvider('annotationDataProvider')]
    public function testFormattingAnnotation(
        PublishableAnnotationInterface $annotation,
        string $expectedTitle,
        string $expectedMessage
    ): void {
        $formatter = new CheckAnnotationFormatterService();

        $this->assertEquals($expectedTitle, $formatter->formatTitle());
        $this->assertEquals($expectedMessage, $formatter->format($annotation));
    }

    public static function annotationDataProvider(): array
    {
        $event = new UploadsFinalised(
            provider: Provider::GITHUB,
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-ref',
            commit: 'mock-commit',
            parent: []
        );

        return [
            [
                new PublishableMissingCoverageAnnotationMessage(
                    event: $event,
                    fileName: 'mock-file',
                    startingOnMethod: false,
                    startLineNumber: 1,
                    endLineNumber: 10,
                    validUntil: $event->getEventTime()
                ),
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
                'Opportunity For New Coverage',
                'The next 99 lines are not covered by any tests.'
            ],
        ];
    }
}
