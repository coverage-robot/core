<?php

namespace App\Tests\Service;

use App\Service\PathFixingService;
use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Model\PathReplacement;
use Packages\Configuration\Service\SettingService;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PathFixingServiceTest extends TestCase
{
    #[DataProvider('configurationDataProvider')]
    public function testFixingPathsWithDifferentSettings(
        string $path,
        string $projectRoot,
        array $pathReplacements,
        string $expectedPath
    ): void {
        $mockSettingService = $this->createMock(SettingService::class);
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

        $this->assertEquals($expectedPath, $fixedPath);
    }

    public static function configurationDataProvider(): array
    {
        return [
            [
                'project-root/src/path/to/file',
                'project-root/',
                [],
                'src/path/to/file'
            ],
            [
                'path-replacement/src/path/to/file',
                '',
                [
                    new PathReplacement(
                        'path-replacement/',
                        ''
                    )
                ],
                'src/path/to/file'
            ],
            [
                'path/some-value/replacement/src/path/to/file',
                '',
                [
                    new PathReplacement(
                        'path/.*/replacement/',
                        'a-replacement/'
                    )
                ],
                'a-replacement/src/path/to/file'
            ],
            [
                'project-root/path/some-value/replacement/src/path/to/file',
                'project-root/',
                [
                    new PathReplacement(
                        '^path/.*/replacement/',
                        'a-replacement/'
                    )
                ],
                'path/some-value/replacement/src/path/to/file'
            ]
        ];
    }
}
