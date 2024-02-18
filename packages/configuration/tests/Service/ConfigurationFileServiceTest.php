<?php

namespace Packages\Configuration\Tests\Service;

use Packages\Configuration\Client\DynamoDbClientInterface;
use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Model\LineCommentType;
use Packages\Configuration\Service\ConfigurationFileService;
use Packages\Configuration\Service\SettingService;
use Packages\Configuration\Service\SettingServiceInterface;
use Packages\Configuration\Setting\LineCommentTypeSetting;
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

final class ConfigurationFileServiceTest extends TestCase
{
    #[DataProvider('configurationFileDataProvider')]
    public function testParsingToFile(
        string $yaml,
        array $expectedParsedFile
    ): void {
        $configurationFileService = new ConfigurationFileService(
            new SettingService(
                [
                    SettingKey::LINE_COMMENT_TYPE->value => new LineCommentTypeSetting(
                        $this->createMock(DynamoDbClientInterface::class)
                    ),
                    SettingKey::PATH_REPLACEMENTS->value => new PathReplacementsSetting(
                        $this->createMock(DynamoDbClientInterface::class),
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
        $mockSettingService = $this->createMock(SettingServiceInterface::class);
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
                line_comment:
                    type: review_comment

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
                line_comment:
                    type: annotation
                YAML,
                [
                    'line_comment.type' => LineCommentType::ANNOTATION
                ]
            ],
            [
                <<<YAML
                line_comment:
                    type: hidden
                YAML,
                [
                    'line_comment.type' => LineCommentType::HIDDEN
                ]
            ],
            [
                <<<YAML
                line_comment:
                    type: review_comment
                YAML,
                [
                    'line_comment.type' => LineCommentType::REVIEW_COMMENT
                ]
            ],
            [
                <<<YAML
                line_comment:
                    type: some-other-value
                YAML,
                []
            ],
            [
                <<<YAML
                line_comment:
                    type:
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
