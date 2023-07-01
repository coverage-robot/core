<?php

namespace App\Tests\Strategy;

use App\Exception\ParseException;
use App\Strategy\ParseStrategyInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

abstract class AbstractParseStrategyTestCase extends TestCase
{
    #[DataProvider('coverageFilesDataProvider')]
    public function testSupports(string $projectRoot, string $contents, bool $expectedSupport): void
    {
        $parser = $this->getParserStrategy();
        $this->assertEquals($expectedSupport, $parser->supports($contents));
    }

    #[DataProvider('coverageFilesDataProvider')]
    public function testParse(
        string $projectRoot,
        string $contents,
        bool $expectedSupport,
        array $expectedCoverage
    ): void {
        $parser = $this->getParserStrategy();
        if (!$expectedSupport) {
            $this->expectException(ParseException::class);
        }

        $projectCoverage = $parser->parse($projectRoot, $contents);

        if ($expectedSupport) {
            $this->assertSame(
                $expectedCoverage,
                json_decode(json_encode($projectCoverage), true)
            );
        }
    }

    abstract public static function coverageFilesDataProvider(): array;

    abstract protected function getParserStrategy(): ParseStrategyInterface;

    protected static function parseCoverageFixtures(string $path, string $fileExtension): array
    {
        return array_reduce(
            glob(sprintf('%s/*.%s', $path, $fileExtension)),
            static fn (array $fixtures, string $path) =>
                [
                    ...$fixtures,
                    sprintf('Can handle %s', basename($path)) => [
                        'mock/project/root',
                        file_get_contents($path),
                        true,
                        json_decode(
                            file_get_contents(
                                substr($path, 0, strlen($path) - strlen($fileExtension)) . 'json'
                            ),
                            true
                        ),
                    ]
                ],
            []
        );
    }
}
