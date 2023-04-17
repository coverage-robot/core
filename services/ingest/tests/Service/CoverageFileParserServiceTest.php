<?php

namespace App\Tests\Service;

use App\Model\ProjectCoverage;
use App\Service\CoverageFileParserService;
use App\Strategy\Clover\PhpCloverParseStrategy;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CoverageFileParserServiceTest extends KernelTestCase
{
    #[DataProvider('coverageFilePathDataProvider')]
    public function testParse(string $path, string $expectedStrategy)
    {
        $container = self::getContainer();
        $coverageFile = file_get_contents($path);
        $coverage = new ProjectCoverage();

        foreach (CoverageFileParserService::getSubscribedServices() as $subscribedStrategy) {
            $mockStrategy = $this->createMock($subscribedStrategy);
            $mockStrategy->expects($this->atMost(1))
                ->method("supports")
                ->with($coverageFile)
                ->willReturn($expectedStrategy === $subscribedStrategy);

            $mockStrategy->expects($this->exactly($expectedStrategy === $subscribedStrategy ? 1 : 0))
                ->method("parse")
                ->with($coverageFile)
                ->willReturn($coverage);

            $container->set($subscribedStrategy, $mockStrategy);
        }

        $coverageFileParserService = new CoverageFileParserService($container);
        $this->assertEquals($coverage, $coverageFileParserService->parse($coverageFile));
    }

    public static function coverageFilePathDataProvider(): array
    {
        return [
            "Clover XML file (PHP variant)" => [
                __DIR__ . "/../Clover/simple-php-coverage.xml",
                PhpCloverParseStrategy::class
            ],
            "Clover XML file (PHP variant) 2" => [
                __DIR__ . "/../Clover/complex-php-coverage.xml",
                PhpCloverParseStrategy::class
            ]
        ];
    }
}
