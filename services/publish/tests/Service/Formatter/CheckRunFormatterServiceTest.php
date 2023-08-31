<?php

namespace App\Tests\Service\Formatter;

use App\Service\Formatter\CheckRunFormatterService;
use PHPUnit\Framework\TestCase;

class CheckRunFormatterServiceTest extends TestCase
{
    public function testFormatTitle(): void
    {
        $formatter = new CheckRunFormatterService();

        $this->assertEquals('Coverage Robot', $formatter->formatTitle());
    }

    public function testFormatSummary(): void
    {
        $formatter = new CheckRunFormatterService();

        $this->assertEquals('', $formatter->formatSummary());
    }
}
