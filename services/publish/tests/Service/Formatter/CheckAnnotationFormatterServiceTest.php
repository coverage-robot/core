<?php

namespace App\Tests\Service\Formatter;

use App\Service\Formatter\CheckAnnotationFormatterService;
use Packages\Event\Model\Upload;
use Packages\Message\PublishableMessage\PublishableCheckAnnotationMessage;
use Packages\Models\Enum\LineState;
use PHPUnit\Framework\TestCase;

class CheckAnnotationFormatterServiceTest extends TestCase
{
    public function testFormatTitleWithCoveredLine(): void
    {
        $annotationMessage = new PublishableCheckAnnotationMessage(
            $this->createMock(Upload::class),
            '/file.php',
            1,
            LineState::COVERED,
            new \DateTimeImmutable()
        );

        $formatter = new CheckAnnotationFormatterService();

        $this->assertEquals('Covered Line', $formatter->formatTitle($annotationMessage));
    }

    public function testFormatTitleWithUncoveredLine(): void
    {
        $annotationMessage = new PublishableCheckAnnotationMessage(
            $this->createMock(Upload::class),
            '/file.php',
            1,
            LineState::UNCOVERED,
            new \DateTimeImmutable()
        );

        $formatter = new CheckAnnotationFormatterService();

        $this->assertEquals('Uncovered Line', $formatter->formatTitle($annotationMessage));
    }

    public function testFormatMessageWithCoveredLine(): void
    {
        $annotationMessage = new PublishableCheckAnnotationMessage(
            $this->createMock(Upload::class),
            '/file.php',
            1,
            LineState::COVERED,
            new \DateTimeImmutable()
        );

        $formatter = new CheckAnnotationFormatterService();

        $this->assertEquals('This line is covered by a test.', $formatter->format($annotationMessage));
    }

    public function testFormatMessageWithUncoveredLine(): void
    {
        $annotationMessage = new PublishableCheckAnnotationMessage(
            $this->createMock(Upload::class),
            '/file.php',
            1,
            LineState::UNCOVERED,
            new \DateTimeImmutable()
        );

        $formatter = new CheckAnnotationFormatterService();

        $this->assertEquals('This line is not covered by a test.', $formatter->format($annotationMessage));
    }
}
