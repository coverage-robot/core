<?php

namespace Packages\Configuration\Tests\Service;

use Packages\Configuration\Client\DynamoDbClient;
use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Service\ConfigurationFileService;
use Packages\Configuration\Service\SettingService;
use Packages\Configuration\Setting\LineAnnotationSetting;
use Packages\Configuration\Setting\PathReplacementsSetting;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\UidNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validation;

class ConfigurationFileServiceTest extends TestCase
{
    #[DataProvider('configurationFileDataProvider')]
    public function testParsingToFile(
        string $yaml,
        array $expectedParsedFile
    ): void {
        $configurationFileService = new ConfigurationFileService(
            new SettingService(
                [
                    SettingKey::LINE_ANNOTATION->value => new LineAnnotationSetting(
                        $this->createMock(DynamoDbClient::class)
                    ),
                    SettingKey::PATH_REPLACEMENTS->value => new PathReplacementsSetting(
                        $this->createMock(DynamoDbClient::class),
                        new Serializer(
                            [
                                new ArrayDenormalizer(),
                                new UidNormalizer(),
                                new BackedEnumNormalizer(),
                                new DateTimeNormalizer(),
                            ],
                            [new JsonEncoder()]
                        ),
                        Validation::createValidatorBuilder()
                            ->enableAttributeMapping()
                            ->getValidator()
                    )
                ]
            )
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

    public function testParseAndPersistFile(): void
    {
        $mockSettingService = $this->createMock(SettingService::class);
        $mockSettingService->expects($this->exactly(4))
            ->method('deserialize')
            ->willReturn('');
        $mockSettingService->expects($this->exactly(4))
            ->method('set')
            ->willReturn(true);
        $mockSettingService->expects($this->never())
            ->method('delete')
            ->willReturn(true);

        $configurationFileService = new ConfigurationFileService(
            $mockSettingService
        );

        $this->assertTrue(
            $configurationFileService->parseAndPersistFile(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                <<<YAML
                line_annotations: false

                path_replacements:
                    - before: a
                      after: b
                    - before: c
                      after: d

                tag_behaviour:
                    default:
                        carryforward: true

                    tags:
                        - name: a
                          carryforward: false
                        - name: b
                          carryforward: true
                YAML
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
                []
            ],
            [
                <<<YAML
                line_annotations:
                    - a
                    - b
                    - c
                YAML,
                []
            ],
            [
                '',
                []
            ]
        ];
    }
}
