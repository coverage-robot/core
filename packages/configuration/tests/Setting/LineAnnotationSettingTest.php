<?php

namespace Packages\Configuration\Tests\Setting;

use Packages\Configuration\Client\DynamoDbClient;
use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Enum\SettingValueType;
use Packages\Configuration\Exception\InvalidSettingValueException;
use Packages\Configuration\Setting\LineAnnotationSetting;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

class LineAnnotationSettingTest extends TestCase
{
    #[DataProvider('trueFalseDataProvider')]
    public function testSettingLineAnnotationSetting(bool $settingValue): void
    {
        $mockDynamoDbClient = $this->createMock(DynamoDbClient::class);
        $mockDynamoDbClient->expects($this->once())
            ->method('setSettingInStore')
            ->with(
                Provider::GITHUB,
                'owner',
                'repository',
                SettingKey::LINE_ANNOTATION,
                SettingValueType::BOOLEAN,
                $settingValue
            )
            ->willReturn(true);

        $lineAnnotationSetting = new LineAnnotationSetting(
            $mockDynamoDbClient
        );

        $this->assertTrue(
            $lineAnnotationSetting->set(
                Provider::GITHUB,
                'owner',
                'repository',
                $settingValue
            )
        );
    }

    #[DataProvider('trueFalseDataProvider')]
    public function testGettingLineAnnotationSetting(bool $settingValue): void
    {
        $mockDynamoDbClient = $this->createMock(DynamoDbClient::class);
        $mockDynamoDbClient->expects($this->once())
            ->method('getSettingFromStore')
            ->with(
                Provider::GITHUB,
                'owner',
                'repository',
                SettingKey::LINE_ANNOTATION,
                SettingValueType::BOOLEAN
            )
            ->willReturn($settingValue);

        $lineAnnotationSetting = new LineAnnotationSetting(
            $mockDynamoDbClient
        );

        $this->assertEquals(
            $settingValue,
            $lineAnnotationSetting->get(
                Provider::GITHUB,
                'owner',
                'repository'
            )
        );
    }

    #[DataProvider('validatingValuesDataProvider')]
    public function testValidatingLineAnnotationValue(mixed $settingValue, bool $expectedValid): void
    {
        $lineAnnotationSetting = new LineAnnotationSetting(
            $this->createMock(DynamoDbClient::class)
        );

        if (!$expectedValid) {
            $this->expectException(InvalidSettingValueException::class);
        } else {
            $this->expectNotToPerformAssertions();
        }

        $lineAnnotationSetting->validate($settingValue);
    }

    public function testSettingKey(): void
    {
        $this->assertEquals(
            SettingKey::LINE_ANNOTATION->value,
            LineAnnotationSetting::getSettingKey()
        );
    }

    public static function trueFalseDataProvider(): array
    {
        return [
            'True' => [true],
            'False' => [false]
        ];
    }

    public static function validatingValuesDataProvider(): array
    {
        return [
            'True' => [
                true,
                true
            ],
            'False' => [
                false,
                true
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
