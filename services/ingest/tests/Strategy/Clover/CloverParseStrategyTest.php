<?php

namespace App\Tests\Strategy\Clover;

use App\Strategy\Clover\CloverParseStrategy;
use App\Strategy\ParseStrategyInterface;
use App\Tests\Strategy\AbstractParseStrategyTestCase;

class CloverParseStrategyTest extends AbstractParseStrategyTestCase
{
    public static function coverageFilesDataProvider(): iterable
    {
        yield from parent::parseCoverageFixtures(__DIR__ . '/../../Fixture/Clover', 'xml');

        yield 'Does not handle invalid file' => [
            'mock/project/root',
            'invalid-file-content',
            false,
            ''
        ];

        yield 'Does not handle invalid schema file' => [
            'mock/project/root',
            <<<XML
                <?xml version="1.0" encoding="UTF-8"?>
                <coverage generatedAt="abc" clover="-99">
                  <project timestamp="1686599618433" name="All files"></project>
                </coverage>
                XML,
            false,
            ''
        ];
    }

    protected function getParserStrategy(): ParseStrategyInterface
    {
        return $this->getContainer()->get(CloverParseStrategy::class);
    }
}
