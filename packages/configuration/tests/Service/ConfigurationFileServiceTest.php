<?php

namespace Packages\Configuration\Tests\Service;

use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Service\ConfigurationFileService;
use Packages\Configuration\Service\SettingService;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConfigurationFileServiceTest extends TestCase
{
    #[DataProvider('configurationFileDataProvider')]
    public function testParsingToFile(
        string $yaml,
        array $expectedParsedFile
    ): void {
        $mockSettingService = $this->createMock(SettingService::class);
        $mockSettingService->expects($this->atLeast(count($expectedParsedFile)))
            ->method('validate')
            ->willReturn(true);
        $mockSettingService->expects($this->never())
            ->method('set');

        $configurationFileService = new ConfigurationFileService(
            $mockSettingService
        );

        $parsedFile = $configurationFileService->parseFile($yaml);

        $this->assertCount(
            count($expectedParsedFile),
            $parsedFile
        );

        foreach ($expectedParsedFile as $expectedKey => $expectedValue) {
            $this->assertEquals(
                $expectedValue,
                $parsedFile[SettingKey::from($expectedKey)],
            );
        }
    }

    #[DataProvider('configurationFileDataProvider')]
    public function testParseAndPersistFile(
        string $yaml,
        array $expectedParsedFile
    ): void {
        $mockSettingService = $this->createMock(SettingService::class);
        $mockSettingService->expects($this->exactly(count($expectedParsedFile)))
            ->method('validate')
            ->willReturn(true);
        $mockSettingService->expects($this->exactly(count($expectedParsedFile)))
            ->method('set')
            ->willReturn(true);

        $configurationFileService = new ConfigurationFileService(
            $mockSettingService
        );

        $this->assertTrue(
            $configurationFileService->parseAndPersistFile(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                $yaml
            )
        );
    }

    public static function configurationFileDataProvider(): array
    {
        return [
            [
                <<<YAML
                line_annotations: true
                YAML,
                [
                    'line_annotations' => true
                ]
            ],
            [
                <<<YAML
                line_annotations: false
                YAML,
                [
                    'line_annotations' => false
                ]
            ],
            [
                <<<YAML
                line_annotations: some-other-value
                YAML,
                [
                    'line_annotations' => 'some-other-value'
                ]
            ],
            [
                <<<YAML
                line_annotations:
                    - a
                    - b
                    - c
                YAML,
                [
                    'line_annotations' => ['a', 'b', 'c']
                ]
            ]
        ];
    }
}
