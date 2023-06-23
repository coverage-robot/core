<?php

namespace App\Tests\Service\Formatter;

use App\Query\Result\LineCoverageQueryResult;
use App\Service\Formatter\CheckAnnotationFormatterService;
use Packages\Models\Enum\LineState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CheckAnnotationFormatterServiceTest extends TestCase
{
    #[DataProvider('lineDataProvider')]
    public function testFormat(LineCoverageQueryResult $result, string $expectedMessage): void
    {
        $formatter = new CheckAnnotationFormatterService();
        $this->assertEquals($expectedMessage, $formatter->format($result));
    }

    public static function lineDataProvider(): array
    {
        return [
            'covered' => [
                LineCoverageQueryResult::from([
                    'fileName' => 'file-1',
                    'lineNumber' => 1,
                    'state' => LineState::COVERED->value
                ]),
                'This line is covered by a test.'
            ],
            'uncovered' => [
                LineCoverageQueryResult::from([
                    'fileName' => 'file-1',
                    'lineNumber' => 1,
                    'state' => LineState::UNCOVERED->value
                ]),
                'This line is not covered by a test.'
            ],
            'partial' => [
                LineCoverageQueryResult::from([
                    'fileName' => 'file-1',
                    'lineNumber' => 1,
                    'state' => LineState::PARTIAL->value
                ]),
                'This line is covered by a test.'
            ],
        ];
    }
}
