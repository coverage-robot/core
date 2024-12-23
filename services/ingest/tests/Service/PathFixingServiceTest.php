<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\PathFixingService;
use Iterator;
use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Model\PathReplacement;
use Packages\Configuration\Service\SettingServiceInterface;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PathFixingServiceTest extends TestCase
{
    #[DataProvider('configurationDataProvider')]
    public function testFixingPathsWithDifferentSettings(
        string $path,
        string $projectRoot,
        array $pathReplacements,
        string $expectedPath
    ): void {
        $mockSettingService = $this->createMock(SettingServiceInterface::class);
        $mockSettingService->expects($this->once())
            ->method('get')
            ->with(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                SettingKey::PATH_REPLACEMENTS
            )
            ->willReturn($pathReplacements);
        $pathFixingService = new PathFixingService($mockSettingService);

        $fixedPath = $pathFixingService->fixPath(
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            $path,
            $projectRoot,
        );

        $this->assertSame($expectedPath, $fixedPath);
    }

    public static function configurationDataProvider(): Iterator
    {
        yield [
            'project-root/src/path/to/file',
            'project-root/',
            [],
            'src/path/to/file'
        ];

        yield [
            'path-replacement/src/path/to/file',
            '',
            [
                new PathReplacement(
                    'path-replacement/',
                    ''
                )
            ],
            'src/path/to/file'
        ];

        yield [
            'path/some-value/replacement/src/path/to/file',
            '',
            [
                new PathReplacement(
                    'path/.*/replacement/',
                    'a-replacement/'
                )
            ],
            'a-replacement/src/path/to/file'
        ];

        yield [
            'project-root/path/some-value/replacement/src/path/to/file',
            'project-root/',
            [
                new PathReplacement(
                    '^path/.*/replacement/',
                    'a-replacement/'
                )
            ],
            'path/some-value/replacement/src/path/to/file'
        ];
    }
}
