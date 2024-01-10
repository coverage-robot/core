<?php

namespace Packages\Configuration\Tests\Setting;

use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use Packages\Configuration\Client\DynamoDbClient;
use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Enum\SettingValueType;
use Packages\Configuration\Exception\InvalidSettingValueException;
use Packages\Configuration\Model\DefaultTagBehaviour;
use Packages\Configuration\Setting\DefaultTagBehaviourSetting;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class DefaultTagBehaviourSettingTest extends TestCase
{
    public function testSettingDefaultTagBehaviourSetting(): void
    {
        $mockDynamoDbClient = $this->createMock(DynamoDbClient::class);
        $mockDynamoDbClient->expects($this->once())
            ->method('setSettingInStore')
            ->with(
                Provider::GITHUB,
                'owner',
                'repository',
                SettingKey::DEFAULT_TAG_BEHAVIOUR,
                SettingValueType::MAP,
                new AttributeValue([
                    SettingValueType::MAP->value => [
                        'carryforward' => [
                            SettingValueType::BOOLEAN->value => false
                        ]
                    ]
                ])
            )
            ->willReturn(true);

        $defaultTagBehaviourSetting = new DefaultTagBehaviourSetting(
            $mockDynamoDbClient,
            $this->createMock(Serializer::class),
            $this->createMock(ValidatorInterface::class)
        );

        $this->assertTrue(
            $defaultTagBehaviourSetting->set(
                Provider::GITHUB,
                'owner',
                'repository',
                new DefaultTagBehaviour(
                    carryforward: false
                )
            )
        );
    }

    public function testGettingDefaultTagBehaviourSetting(): void
    {
        $defaultTagBehaviour = new DefaultTagBehaviour(
            carryforward: false
        );

        $mockDynamoDbClient = $this->createMock(DynamoDbClient::class);
        $mockDynamoDbClient->expects($this->once())
            ->method('getSettingFromStore')
            ->with(
                Provider::GITHUB,
                'owner',
                'repository',
                SettingKey::DEFAULT_TAG_BEHAVIOUR,
                SettingValueType::MAP
            )
            ->willReturn(
                $defaultTagBehaviour
            );

        $defaultTagBehaviourSetting = new DefaultTagBehaviourSetting(
            $mockDynamoDbClient,
            $this->createMock(Serializer::class),
            $this->createMock(ValidatorInterface::class)
        );

        $this->assertEquals(
            $defaultTagBehaviour,
            $defaultTagBehaviourSetting->get(
                Provider::GITHUB,
                'owner',
                'repository'
            )
        );
    }

    #[DataProvider('validatingValuesDataProvider')]
    public function testValidatingDefaultTagBehaviourValue(mixed $settingValue, bool $expectedValid): void
    {
        $defaultTagBehaviourSetting = new DefaultTagBehaviourSetting(
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

        $defaultTagBehaviourSetting->validate($settingValue);
    }

    public function testSettingKey(): void
    {
        $this->assertEquals(
            SettingKey::DEFAULT_TAG_BEHAVIOUR->value,
            DefaultTagBehaviourSetting::getSettingKey()
        );
    }

    public static function validatingValuesDataProvider(): array
    {
        return [
            'No carrying forward tag behaviour' => [
                new DefaultTagBehaviour(
                    carryforward: false
                ),
                true
            ],
            'Carrying forward tag behaviour' => [
                new DefaultTagBehaviour(
                    carryforward: true
                ),
                true
            ],
            'True' => [
                true,
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
            'Array' => [
                [],
                false
            ],
            'Object' => [
                new stdClass(),
                false
            ],
        ];
    }
}
