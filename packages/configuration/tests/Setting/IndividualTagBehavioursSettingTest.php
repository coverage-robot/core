<?php

namespace Packages\Configuration\Tests\Setting;

use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use Packages\Configuration\Client\DynamoDbClient;
use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Enum\SettingValueType;
use Packages\Configuration\Exception\InvalidSettingValueException;
use Packages\Configuration\Model\IndividualTagBehaviour;
use Packages\Configuration\Model\PathReplacement;
use Packages\Configuration\Setting\IndividualTagBehavioursSetting;
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

class IndividualTagBehavioursSettingTest extends TestCase
{
    /**
     * @throws ExceptionInterface
     * @throws Exception
     */
    public function testSettingIndvidualTagBehaviours(): void
    {
        $mockDynamoDbClient = $this->createMock(DynamoDbClient::class);
        $mockDynamoDbClient->expects($this->once())
            ->method('setSettingInStore')
            ->with(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                SettingKey::INDIVIDUAL_TAG_BEHAVIOURS,
                SettingValueType::LIST,
                [
                    new AttributeValue([
                        SettingValueType::MAP->value => [
                            'name' => new AttributeValue([
                                SettingValueType::STRING->value => 'mock-tag'
                            ]),
                            'carryforward' => new AttributeValue([
                                SettingValueType::BOOLEAN->value => true
                            ])
                        ]
                    ])
                ]
            )
            ->willReturn(true);
        $individualTagBehavioursSetting = new IndividualTagBehavioursSetting(
            $mockDynamoDbClient,
            $this->createMock(Serializer::class),
            $this->createMock(ValidatorInterface::class)
        );

        $this->assertTrue(
            $individualTagBehavioursSetting->set(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                [
                    new IndividualTagBehaviour(
                        'mock-tag',
                        true
                    )
                ]
            )
        );
    }

    /**
     * @throws ExceptionInterface
     * @throws Exception
     */
    public function testGettingIndividualTagBehaviours(): void
    {
        $mockDynamoDbClient = $this->createMock(DynamoDbClient::class);
        $mockDynamoDbClient->expects($this->once())
            ->method('getSettingFromStore')
            ->with(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                SettingKey::INDIVIDUAL_TAG_BEHAVIOURS,
                SettingValueType::LIST
            )
            ->willReturn(
                [
                    new AttributeValue([
                        SettingValueType::MAP->value => [
                            'name' => new AttributeValue([
                                SettingValueType::STRING->value => 'mock-tag'
                            ]),
                            'carryforward' => new AttributeValue([
                                SettingValueType::BOOLEAN->value => true
                            ])
                        ]
                    ]),
                    new AttributeValue([
                        SettingValueType::MAP->value => [
                            'name' => new AttributeValue([
                                SettingValueType::STRING->value => 'mock-tag-2'
                            ]),
                            'carryforward' => new AttributeValue([
                                SettingValueType::BOOLEAN->value => false
                            ])
                        ]
                    ])
                ]
            );
        $individualTagBehavioursSetting = new IndividualTagBehavioursSetting(
            $mockDynamoDbClient,
            $this->createMock(Serializer::class),
            $this->createMock(ValidatorInterface::class)
        );

        $this->assertEquals(
            [
                new IndividualTagBehaviour(
                    'mock-tag',
                    true
                ),
                new IndividualTagBehaviour(
                    'mock-tag-2',
                    false
                )
            ],
            $individualTagBehavioursSetting->get(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository'
            )
        );
    }

    public function testDeletingIndividualTagBehaviours(): void
    {
        $mockDynamoDbClient = $this->createMock(DynamoDbClient::class);
        $mockDynamoDbClient->expects($this->once())
            ->method('deleteSettingFromStore')
            ->with(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                SettingKey::INDIVIDUAL_TAG_BEHAVIOURS
            )
            ->willReturn(true);
        $individualTagBehavioursSetting = new IndividualTagBehavioursSetting(
            $mockDynamoDbClient,
            $this->createMock(Serializer::class),
            $this->createMock(ValidatorInterface::class)
        );

        $this->assertTrue(
            $individualTagBehavioursSetting->delete(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository'
            )
        );
    }

    #[DataProvider('validatingValuesDataProvider')]
    public function testValidatingIndividualTagBehavioursValue(mixed $settingValue, bool $expectedValid): void
    {
        $individualTagBehavioursSetting = new IndividualTagBehavioursSetting(
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

        $individualTagBehavioursSetting->validate($settingValue);
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
                    new IndividualTagBehaviour(
                        'mock-tag',
                        true
                    )
                ],
                true
            ],
            'Multiple valid path replacements' => [
                [
                    new IndividualTagBehaviour(
                        'mock-tag',
                        true
                    ),
                    new IndividualTagBehaviour(
                        'mock-tag',
                        false
                    ),
                    new IndividualTagBehaviour(
                        'mock-tag-2',
                        false
                    )
                ],
                true
            ],
            'Multiple invalid behaviours' => [
                [
                    new IndividualTagBehaviour(
                        '',
                        false
                    ),
                    new IndividualTagBehaviour(
                        '',
                        true
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
