<?php

namespace Packages\Configuration\Tests\Service;

use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Exception\InvalidSettingValueException;
use Packages\Configuration\Service\SettingService;
use Packages\Configuration\Setting\SettingInterface;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SettingServiceTest extends TestCase
{
    #[DataProvider('trueFalseDataProvider')]
    public function testSettingValue(bool $isSetSuccessful): void
    {
        $mockSetting = $this->createMock(SettingInterface::class);
        $mockSetting->expects($this->once())
            ->method('set')
            ->with(
                Provider::GITHUB,
                'owner',
                'repository',
                'value'
            )
            ->willReturn($isSetSuccessful);

        $settingService = new SettingService(
            [
                SettingKey::LINE_COMMENT_TYPE->value => $mockSetting
            ]
        );

        $this->assertEquals(
            $isSetSuccessful,
            $settingService->set(
                Provider::GITHUB,
                'owner',
                'repository',
                SettingKey::LINE_COMMENT_TYPE,
                'value'
            )
        );
    }

    #[DataProvider('trueFalseDataProvider')]
    public function testDeserializingValue(bool $isValidateSuccessful): void
    {
        $mockSetting = $this->createMock(SettingInterface::class);

        if (!$isValidateSuccessful) {
            $mockSetting->expects($this->once())
                ->method('deserialize')
                ->willThrowException(new InvalidSettingValueException());

            $this->expectException(InvalidSettingValueException::class);
        } else {
            $mockSetting->expects($this->once())
                ->method('deserialize')
                ->with('value')
                ->willReturn('value');
        }

        $settingService = new SettingService(
            [
                SettingKey::LINE_COMMENT_TYPE->value => $mockSetting
            ]
        );

        $value = $settingService->deserialize(
            SettingKey::LINE_COMMENT_TYPE,
            'value'
        );

        if ($isValidateSuccessful) {
            $this->assertEquals(
                'value',
                $value
            );
        }
    }

    #[DataProvider('settingValueDataProvider')]
    public function testGettingValue(mixed $settingValue): void
    {
        $mockSetting = $this->createMock(SettingInterface::class);
        $mockSetting->expects($this->once())
            ->method('get')
            ->with(
                Provider::GITHUB,
                'owner',
                'repository'
            )
            ->willReturn($settingValue);

        $settingService = new SettingService(
            [
                SettingKey::LINE_COMMENT_TYPE->value => $mockSetting
            ]
        );

        $this->assertEquals(
            $settingValue,
            $settingService->get(
                Provider::GITHUB,
                'owner',
                'repository',
                SettingKey::LINE_COMMENT_TYPE
            )
        );
    }

    #[DataProvider('trueFalseDataProvider')]
    public function testDeletingValue(mixed $isDeleteSuccessful): void
    {
        $mockSetting = $this->createMock(SettingInterface::class);
        $mockSetting->expects($this->once())
            ->method('delete')
            ->with(
                Provider::GITHUB,
                'owner',
                'repository'
            )
            ->willReturn($isDeleteSuccessful);

        $settingService = new SettingService(
            [
                SettingKey::LINE_COMMENT_TYPE->value => $mockSetting
            ]
        );

        $this->assertEquals(
            $isDeleteSuccessful,
            $settingService->delete(
                Provider::GITHUB,
                'owner',
                'repository',
                SettingKey::LINE_COMMENT_TYPE
            )
        );
    }

    public static function trueFalseDataProvider(): array
    {
        return [
            'True' => [true],
            'False' => [false]
        ];
    }

    public static function settingValueDataProvider(): array
    {
        return [
            [
                'some-string'
            ],
            [
                'true'
            ],
            [
                true
            ],
            [
                false
            ]
        ];
    }
}
