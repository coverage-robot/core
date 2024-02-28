<?php

namespace Packages\Configuration\Tests\Setting;

use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use Packages\Configuration\Client\DynamoDbClientInterface;
use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Enum\SettingValueType;
use Packages\Configuration\Model\PathReplacement;
use Packages\Configuration\Setting\PathReplacementsSetting;
use Packages\Contracts\Provider\Provider;
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

final class PathReplacementsSettingTest extends TestCase
{
    /**
     * @throws ExceptionInterface
     * @throws Exception
     */
    public function testSettingPathReplacements(): void
    {
        $mockDynamoDbClient = $this->createMock(DynamoDbClientInterface::class);
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
                        SettingValueType::MAP->value => [
                            'before' => new AttributeValue([
                                SettingValueType::STRING->value => 'path'
                            ]),
                            'after' => new AttributeValue([
                                SettingValueType::STRING->value => 'replacement'
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
        $mockDynamoDbClient = $this->createMock(DynamoDbClientInterface::class);
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
                        SettingValueType::MAP->value => [
                            'before' => new AttributeValue([
                                SettingValueType::STRING->value => 'path'
                            ]),
                            'after' => new AttributeValue([
                                SettingValueType::STRING->value => 'replacement'
                            ])
                        ]
                    ]),
                    new AttributeValue([
                        SettingValueType::MAP->value => [
                            'before' => new AttributeValue([
                                SettingValueType::STRING->value => 'path'
                            ]),
                            'after' => new AttributeValue([
                                SettingValueType::STRING->value => 'replacement'
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
        $mockDynamoDbClient = $this->createMock(DynamoDbClientInterface::class);
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

    public function testDeserializingPathReplacements(): void
    {
        $pathReplacementsSetting = new PathReplacementsSetting(
            $this->createMock(DynamoDbClientInterface::class),
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
                        SettingValueType::MAP->value => [
                            'before' => new AttributeValue([
                                SettingValueType::STRING->value => 'path-1'
                            ]),
                            'after' => new AttributeValue([
                                SettingValueType::STRING->value => 'replacement-1'
                            ])
                        ]
                    ]),
                    new AttributeValue([
                        SettingValueType::MAP->value => [
                            'before' => new AttributeValue([
                                SettingValueType::STRING->value => 'path-2'
                            ]),
                            'after' => new AttributeValue([
                                SettingValueType::STRING->value => 'replacement-2'
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
            SettingValueType::NULL->value => [
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
