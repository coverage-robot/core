<?php

declare(strict_types=1);

namespace App\Tests\Strategy\GoCover;

use App\Service\PathFixingService;
use App\Strategy\GoCover\GoCoverParseStrategy;
use App\Strategy\ParseStrategyInterface;
use App\Tests\Strategy\AbstractParseStrategyTestCase;
use Override;
use Packages\Configuration\Model\PathReplacement;
use Packages\Configuration\Service\SettingServiceInterface;
use Psr\Log\NullLogger;

final class GoCoverParseStrategyTest extends AbstractParseStrategyTestCase
{
    #[Override]
    public static function coverageFilesDataProvider(): iterable
    {
        yield from parent::parseCoverageFixtures(__DIR__ . '/../../Fixture/GoCover');

        yield 'Does not handle invalid file' => [
            'mock/project/root',
            'invalid-file-content',
            false
        ];
    }

    #[Override]
    protected function getParserStrategy(): ParseStrategyInterface
    {
        $mockSettingService = $this->createMock(SettingServiceInterface::class);
        $mockSettingService->method('get')
            ->willReturn([
                new PathReplacement(
                    'mock/path/to/replace/',
                    ''
                )
            ]);

        return new GoCoverParseStrategy(
            new NullLogger(),
            new PathFixingService($mockSettingService, new NullLogger())
        );
    }
}
