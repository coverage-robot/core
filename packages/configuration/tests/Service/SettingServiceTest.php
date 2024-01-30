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
    public function testSettingValueOnlySetsWhenCacheIsNotIdentical(bool $isSetSuccessful): void
    {
        $mockSetting = $this->createMock(SettingInterface::class);
        $mockSetting->expects($this->exactly($isSetSuccessful ? 1 : 2))
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
                SettingKey::LINE_ANNOTATION->value => $mockSetting
            ]
        );

        $this->assertEquals(
            $isSetSuccessful,
            $settingService->set(
                Provider::GITHUB,
                'owner',
                'repository',
                SettingKey::LINE_ANNOTATION,
                'value'
            )
        );

        $this->assertEquals(
            $isSetSuccessful,
            $settingService->set(
                Provider::GITHUB,
                'owner',
                'repository',
                SettingKey::LINE_ANNOTATION,
                'value'
            )
        );
    }

    #[DataProvider('trueFalseDataProvider')]
    public function testDeserializingValue(bool $isValidateSuccessful): void
    {
        $mockSetting = $this->createMock(SettingInterface::class);
        $mockSetting->expects($this->once())
            ->method('deserialize')
            ->with('value')
            ->willReturn('value');

        if (!$isValidateSuccessful) {
            $mockSetting->expects($this->once())
                ->method('validate')
                ->willThrowException(new InvalidSettingValueException());

            $this->expectException(InvalidSettingValueException::class);
        } else {
            $mockSetting->expects($this->once())
                ->method('validate');
        }

        $settingService = new SettingService(
            [
                SettingKey::LINE_ANNOTATION->value => $mockSetting
            ]
        );

        $value = $settingService->deserialize(
            SettingKey::LINE_ANNOTATION,
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
    public function testGettingValueUsesCacheForSubsequentCalls(mixed $settingValue): void
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
                SettingKey::LINE_ANNOTATION->value => $mockSetting
            ]
        );

        $retrievedValue = $settingService->get(
            Provider::GITHUB,
            'owner',
            'repository',
            SettingKey::LINE_ANNOTATION
        );
        $this->assertEquals($settingValue, $retrievedValue);

        $mockSetting->expects($this->never())
            ->method('get');
        $this->assertEquals($settingValue, $retrievedValue);
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
                SettingKey::LINE_ANNOTATION->value => $mockSetting
            ]
        );

        $this->assertEquals(
            $isDeleteSuccessful,
            $settingService->delete(
                Provider::GITHUB,
                'owner',
                'repository',
                SettingKey::LINE_ANNOTATION
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
