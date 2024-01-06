<?php

namespace Packages\Configuration\Tests\Setting;

use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use Packages\Configuration\Client\DynamoDbClient;
use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Enum\SettingValueType;
use Packages\Configuration\Exception\InvalidSettingValueException;
use Packages\Configuration\Model\PathReplacement;
use Packages\Configuration\Setting\PathReplacementsSetting;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class PathReplacementsSettingTest extends TestCase
{
    /**
     * @throws ExceptionInterface
     * @throws Exception
     */
    public function testSettingPathReplacements(): void
    {
        $mockDynamoDbClient = $this->createMock(DynamoDbClient::class);
        $mockDynamoDbClient->expects($this->once())
            ->method('setSettingInStore')
            ->with(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                SettingKey::PATH_REPLACEMENTS,
                SettingValueType::LIST,
                [
                    new AttributeValue([
                        'M' => [
                            'before' => new AttributeValue([
                                'S' => 'path'
                            ]),
                            'after' => new AttributeValue([
                                'S' => 'replacement'
                            ])
                        ]
                    ])
                ]
            )
            ->willReturn(true);
        $pathReplacementsSetting = new PathReplacementsSetting(
            $mockDynamoDbClient,
            $this->createMock(Serializer::class),
            $this->createMock(ValidatorInterface::class)
        );

        $this->assertTrue(
            $pathReplacementsSetting->set(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                [
                    new PathReplacement(
                        'path',
                        'replacement'
                    )
                ]
            )
        );
    }

    /**
     * @throws ExceptionInterface
     * @throws Exception
     */
    public function testGettingPathReplacements(): void
    {
        $mockDynamoDbClient = $this->createMock(DynamoDbClient::class);
        $mockDynamoDbClient->expects($this->once())
            ->method('getSettingFromStore')
            ->with(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                SettingKey::PATH_REPLACEMENTS,
                SettingValueType::LIST
            )
            ->willReturn(
                [
                    new AttributeValue([
                        'M' => [
                            'before' => new AttributeValue([
                                'S' => 'path'
                            ]),
                            'after' => new AttributeValue([
                                'S' => 'replacement'
                            ])
                        ]
                    ]),
                    new AttributeValue([
                        'M' => [
                            'before' => new AttributeValue([
                                'S' => 'path'
                            ]),
                            'after' => new AttributeValue([
                                'S' => 'replacement'
                            ])
                        ]
                    ])
                ]
            );
        $pathReplacementsSetting = new PathReplacementsSetting(
            $mockDynamoDbClient,
            $this->createMock(Serializer::class),
            $this->createMock(ValidatorInterface::class)
        );

        $this->assertEquals(
            [
                new PathReplacement(
                    'path',
                    'replacement'
                ),
                new PathReplacement(
                    'path',
                    'replacement'
                )
            ],
            $pathReplacementsSetting->get(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository'
            )
        );
    }

    public function testDeletingPathReplacements(): void
    {
        $mockDynamoDbClient = $this->createMock(DynamoDbClient::class);
        $mockDynamoDbClient->expects($this->once())
            ->method('deleteSettingFromStore')
            ->with(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                SettingKey::PATH_REPLACEMENTS
            )
            ->willReturn(true);
        $pathReplacementsSetting = new PathReplacementsSetting(
            $mockDynamoDbClient,
            $this->createMock(Serializer::class),
            $this->createMock(ValidatorInterface::class)
        );

        $this->assertTrue(
            $pathReplacementsSetting->delete(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository'
            )
        );
    }

    #[DataProvider('validatingValuesDataProvider')]
    public function testValidatingPathReplacementsValue(mixed $settingValue, bool $expectedValid): void
    {
        $pathReplacementsSetting = new PathReplacementsSetting(
            $this->createMock(DynamoDbClient::class),
            $this->createMock(Serializer::class),
            Validation::createValidatorBuilder()
                ->enableAttributeMapping()
                ->getValidator()
        );

        if (!$expectedValid) {
            $this->expectException(InvalidSettingValueException::class);
        } else {
            $this->expectNotToPerformAssertions();
        }

        $pathReplacementsSetting->validate($settingValue);
    }

    public function testDeserializingPathReplacements(): void
    {
        $pathReplacementsSetting = new PathReplacementsSetting(
            $this->createMock(DynamoDbClient::class),
            new Serializer(
                [
                    new ArrayDenormalizer(),
                    new ObjectNormalizer(
                        classMetadataFactory: new ClassMetadataFactory(
                            new AttributeLoader()
                        ),
                        nameConverter: new MetadataAwareNameConverter(
                            new ClassMetadataFactory(
                                new AttributeLoader()
                            ),
                            new CamelCaseToSnakeCaseNameConverter()
                        ),
                    ),
                ],
                [new JsonEncoder()]
            ),
            Validation::createValidatorBuilder()
                ->enableAttributeMapping()
                ->getValidator()
        );

        $this->assertEquals(
            [
                new PathReplacement(
                    'path-1',
                    'replacement-1'
                ),
                new PathReplacement(
                    'path-2',
                    'replacement-2'
                ),
                new PathReplacement(
                    'path-3',
                    'replacement-3'
                ),
                new PathReplacement(
                    'path-4',
                    'replacement-4'
                )
            ],
            $pathReplacementsSetting->deserialize(
                [
                    new AttributeValue([
                        'M' => [
                            'before' => new AttributeValue([
                                'S' => 'path-1'
                            ]),
                            'after' => new AttributeValue([
                                'S' => 'replacement-1'
                            ])
                        ]
                    ]),
                    new AttributeValue([
                        'M' => [
                            'before' => new AttributeValue([
                                'S' => 'path-2'
                            ]),
                            'after' => new AttributeValue([
                                'S' => 'replacement-2'
                            ])
                        ]
                    ]),
                    [
                        'before' => 'path-3',
                        'after' => 'replacement-3'
                    ],
                    new PathReplacement(
                        'path-4',
                        'replacement-4'
                    )
                ]
            )
        );
    }

    public function testSettingKey(): void
    {
        $this->assertEquals(
            SettingKey::PATH_REPLACEMENTS->value,
            PathReplacementsSetting::getSettingKey()
        );
    }

    public static function validatingValuesDataProvider(): array
    {
        return [
            'Valid path replacement' => [
                [
                    new PathReplacement(
                        'path',
                        'replacement'
                    )
                ],
                true
            ],
            'Multiple valid path replacements' => [
                [
                    new PathReplacement(
                        'path',
                        'replacement'
                    ),
                    new PathReplacement(
                        'path',
                        ''
                    ),
                    new PathReplacement(
                        'path',
                        null
                    )
                ],
                true
            ],
            'Multiple invalid path replacements' => [
                [
                    new PathReplacement(
                        '',
                        'replacement'
                    ),
                    new PathReplacement(
                        '',
                        ''
                    )
                ],
                false
            ],
            'False' => [
                false,
                false
            ],
            'Null' => [
                null,
                false
            ],
            'String' => [
                'string',
                false
            ],
            'Integer' => [
                1,
                false
            ],
            'Float' => [
                1.1,
                false
            ],
            'Empty array' => [
                [],
                true
            ],
            'Object' => [
                new stdClass(),
                false
            ],
        ];
    }
}
