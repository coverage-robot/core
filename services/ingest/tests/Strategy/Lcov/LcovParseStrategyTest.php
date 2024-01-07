<?php

namespace App\Tests\Strategy\Lcov;

use App\Service\PathFixingService;
use App\Strategy\Lcov\LcovParseStrategy;
use App\Strategy\ParseStrategyInterface;
use App\Tests\Strategy\AbstractParseStrategyTestCase;
use Override;
use Packages\Configuration\Model\PathReplacement;
use Packages\Configuration\Service\SettingService;
use Psr\Log\NullLogger;

class LcovParseStrategyTest extends AbstractParseStrategyTestCase
{
    #[Override]
    public static function coverageFilesDataProvider(): iterable
    {
        yield from parent::parseCoverageFixtures(__DIR__ . '/../../Fixture/Lcov');

        yield 'Does not handle invalid file' => [
            'mock/project/root',
            'invalid-file-content',
            false
        ];
    }

    #[Override]
    protected function getParserStrategy(): ParseStrategyInterface
    {
        $mockSettingService = $this->createMock(SettingService::class);
        $mockSettingService->method('get')
            ->willReturn([
                new PathReplacement(
                    'mock/path/to/replace',
                    ''
                )
            ]);

        return new LcovParseStrategy(
            new NullLogger(),
            new PathFixingService($mockSettingService)
        );
    }
}
