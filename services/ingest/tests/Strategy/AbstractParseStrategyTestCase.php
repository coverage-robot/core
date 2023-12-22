<?php

namespace App\Tests\Strategy;

use App\Exception\ParseException;
use App\Strategy\ParseStrategyInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Spatie\Snapshots\MatchesSnapshots;

abstract class AbstractParseStrategyTestCase extends TestCase
{
    use MatchesSnapshots;

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
        bool $expectedSupport
    ): void {
        $parser = $this->getParserStrategy();
        if (!$expectedSupport) {
            $this->expectException(ParseException::class);
        }

        $coverage = $parser->parse($projectRoot, $contents);

        if ($expectedSupport) {
            $this->assertMatchesObjectSnapshot($coverage);
        }
    }

    abstract public static function coverageFilesDataProvider(): iterable;

    abstract protected function getParserStrategy(): ParseStrategyInterface;

    protected static function parseCoverageFixtures(string $path): iterable
    {
        foreach (glob(sprintf('%s/*', $path)) as $file) {
            yield sprintf('Can handle %s', basename($file)) => [
                'mock/project/root',
                file_get_contents($file),
                true
            ];
        }
    }
}
