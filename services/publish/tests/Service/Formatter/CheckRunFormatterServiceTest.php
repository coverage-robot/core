<?php

namespace App\Tests\Service\Formatter;

use App\Service\Formatter\CheckRunFormatterService;
use Packages\Message\PublishableMessage\PublishableCheckRunStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CheckRunFormatterServiceTest extends TestCase
{
    #[DataProvider('statusDataProvider')]
    public function testFormatTitle(
        PublishableCheckRunStatus $status,
        float $coveragePercentage,
        float $coverageChange,
        string $expectedTitle
    ): void {
        $formatter = new CheckRunFormatterService();

        $this->assertEquals(
            $expectedTitle,
            $formatter->formatTitle($status, $coveragePercentage, $coverageChange)
        );
    }

    public function testFormatSummary(): void
    {
        $formatter = new CheckRunFormatterService();

        $this->assertEquals('', $formatter->formatSummary());
    }

    public static function statusDataProvider(): array
    {
        return [
            [PublishableCheckRunStatus::SUCCESS, 99.98, 0, 'Total Coverage: 99.98% (0%)'],
            [PublishableCheckRunStatus::SUCCESS, 99.98, 0.01, 'Total Coverage: 99.98% (+0.01%)'],
            [PublishableCheckRunStatus::SUCCESS, 99.98, -0.02, 'Total Coverage: 99.98% (-0.02%)'],
            [PublishableCheckRunStatus::IN_PROGRESS, 0, 0, 'Waiting for any additional coverage uploads...'],
        ];
    }
}
