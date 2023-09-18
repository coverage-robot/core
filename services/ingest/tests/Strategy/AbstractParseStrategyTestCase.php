<?php

namespace App\Tests\Strategy;

use App\Exception\ParseException;
use App\Strategy\ParseStrategyInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

abstract class AbstractParseStrategyTestCase extends KernelTestCase
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
        string $expectedCoverage
    ): void {
        $parser = $this->getParserStrategy();
        if (!$expectedSupport) {
            $this->expectException(ParseException::class);
        }

        $projectCoverage = $parser->parse($projectRoot, $contents);

        if ($expectedSupport) {
            $this->assertJsonStringEqualsJsonString(
                $expectedCoverage,
                $this->getContainer()
                    ->get(SerializerInterface::class)
                    ->serialize($projectCoverage, 'json')
            );
        }
    }

    abstract public static function coverageFilesDataProvider(): iterable;

    abstract protected function getParserStrategy(): ParseStrategyInterface;

    protected static function parseCoverageFixtures(string $path, string $fileExtension): iterable
    {
        foreach (glob(sprintf('%s/*.%s', $path, $fileExtension)) as $file) {
            yield sprintf('Can handle %s', basename($file)) => [
                'mock/project/root',
                file_get_contents($file),
                true,
                file_get_contents(substr($file, 0, strlen($file) - strlen($fileExtension)) . 'json')
            ];
        }
    }
}
