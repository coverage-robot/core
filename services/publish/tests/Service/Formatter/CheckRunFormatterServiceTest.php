<?php

namespace App\Tests\Service\Formatter;

use App\Service\Formatter\CheckRunFormatterService;
use Packages\Models\Enum\PublishableCheckRunStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CheckRunFormatterServiceTest extends TestCase
{
    #[DataProvider('statusDataProvider')]
    public function testFormatTitle(
        PublishableCheckRunStatus $status,
        float $coveragePercentage,
        string $expectedTitle
    ): void {
        $formatter = new CheckRunFormatterService();

        $this->assertEquals(
            $expectedTitle,
            $formatter->formatTitle($status, $coveragePercentage)
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
            [PublishableCheckRunStatus::SUCCESS, 99.98, 'Total Coverage: 99.98%'],
            [PublishableCheckRunStatus::IN_PROGRESS, 0, 'Waiting for any additional coverage uploads...'],
        ];
    }
}
