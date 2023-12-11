<?php

namespace App\Tests\Service\Formatter;

use App\Service\Formatter\CheckAnnotationFormatterService;
use DateTimeImmutable;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Model\UploadsFinalised;
use Packages\Message\PublishableMessage\PublishableAnnotationInterface;
use Packages\Message\PublishableMessage\PublishableMissingCoverageAnnotationMessage;
use Packages\Message\PublishableMessage\PublishablePartialBranchAnnotationMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CheckAnnotationFormatterServiceTest extends TestCase
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
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-ref',
            'mock-commit',
            null,
            new DateTimeImmutable()
        );

        return [
            [
                new PublishableMissingCoverageAnnotationMessage(
                    $event,
                    'mock-file',
                    false,
                    1,
                    10,
                    $event->getEventTime()
                ),
                'Opportunity For New Coverage',
                'The next 9 lines are not covered by any tests.'
            ],
            [
                new PublishableMissingCoverageAnnotationMessage(
                    $event,
                    'mock-file',
                    false,
                    1,
                    1,
                    $event->getEventTime()
                ),
                'Opportunity For New Coverage',
                'This line is not covered by any tests.'
            ],
            [
                new PublishableMissingCoverageAnnotationMessage(
                    $event,
                    'mock-file',
                    false,
                    1,
                    2,
                    $event->getEventTime()
                ),
                'Opportunity For New Coverage',
                'The next 1 lines are not covered by any tests.'
            ],
            [
                new PublishablePartialBranchAnnotationMessage(
                    $event,
                    'mock-file',
                    1,
                    1,
                    2,
                    1,
                    $event->getEventTime()
                ),
                'Opportunity For New Coverage',
                '50% of these branches are not covered by any tests.'
            ],
            [
                new PublishablePartialBranchAnnotationMessage(
                    $event,
                    'mock-file',
                    1,
                    1,
                    1,
                    0,
                    $event->getEventTime()
                ),
                'Opportunity For New Coverage',
                'None of these branches are covered by tests.'
            ],
            [
                new PublishablePartialBranchAnnotationMessage(
                    $event,
                    'mock-file',
                    1,
                    1,
                    5,
                    2,
                    $event->getEventTime()
                ),
                'Opportunity For New Coverage',
                '60% of these branches are not covered by any tests.'
            ],
            [
                new PublishableMissingCoverageAnnotationMessage(
                    $event,
                    'mock-file',
                    true,
                    1,
                    2,
                    $event->getEventTime()
                ),
                'Opportunity For New Coverage',
                'This method has not been covered by any tests.'
            ],
            [
                new PublishableMissingCoverageAnnotationMessage(
                    $event,
                    'mock-file',
                    false,
                    1,
                    100,
                    $event->getEventTime()
                ),
                'Opportunity For New Coverage',
                'The next 99 lines are not covered by any tests.'
            ],
        ];
    }
}
