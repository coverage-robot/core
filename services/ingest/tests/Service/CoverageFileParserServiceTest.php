<?php

namespace App\Tests\Service;

use App\Exception\ParseException;
use App\Model\Coverage;
use App\Service\CoverageFileParserService;
use App\Strategy\ParseStrategyInterface;
use Packages\Contracts\Format\CoverageFormat;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use stdClass;

final class CoverageFileParserServiceTest extends TestCase
{
    public function testParsingSupportedFiles(): void
    {
        $coverage = new Coverage(
            sourceFormat: CoverageFormat::CLOVER,
            root: 'mock/project/root'
        );

        $mockStrategyOne = $this->createMock(ParseStrategyInterface::class);
        $mockStrategyOne->expects($this->once())
            ->method('supports')
            ->with('mock-file')
            ->willReturn(true);
        $mockStrategyOne->expects($this->once())
            ->method('parse')
            ->with(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                'mock-path',
                'mock-file'
            )
            ->willReturn($coverage);

        $mockStrategyTwo = $this->createMock(ParseStrategyInterface::class);
        $mockStrategyTwo->expects($this->never())
            ->method('supports');
        $mockStrategyTwo->expects($this->never())
            ->method('parse');

        $mockStrategyThree = $this->createMock(ParseStrategyInterface::class);
        $mockStrategyThree->expects($this->once())
            ->method('supports')
            ->with('mock-file')
            ->willReturn(false);
        $mockStrategyThree->expects($this->never())
            ->method('parse');

        $coverageFileParserService = new CoverageFileParserService(
            [
                $mockStrategyThree,
                $mockStrategyOne,
                $mockStrategyTwo
            ],
            new NullLogger()
        );
        $this->assertEquals(
            $coverage,
            $coverageFileParserService->parse(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                'mock-path',
                'mock-file'
            )
        );
    }

    public function testInvalidParser(): void
    {
        $mockParser = $this->createMock(ParseStrategyInterface::class);
        $mockParser->expects($this->atMost(1))
            ->method('supports')
            ->with('mock-file')
            ->willReturn(false);

        $invalidParser = $this->createMock(stdClass::class);

        $coverageFileParserService = new CoverageFileParserService(
            [
                $mockParser,
                $invalidParser
            ],
            new NullLogger()
        );

        $this->expectException(ParseException::class);

        $coverageFileParserService->parse(
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-path',
            'mock-file'
        );
    }
}
