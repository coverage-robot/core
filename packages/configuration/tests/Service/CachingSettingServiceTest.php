<?php

namespace Packages\Configuration\Tests\Service;

use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Model\LineCommentType;
use Packages\Configuration\Service\CachingSettingService;
use Packages\Configuration\Service\SettingServiceInterface;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CachingSettingServiceTest extends TestCase
{
    #[DataProvider('settingValueDataProvider')]
    public function testGettingValueUsesCacheForSubsequentCalls(mixed $settingValue): void
    {
        $mockSettingService = $this->createMock(SettingServiceInterface::class);

        $cachingSettingService = new CachingSettingService($mockSettingService);

        $mockSettingService->expects($this->once())
            ->method('get')
            ->with(
                Provider::GITHUB,
                'owner',
                'repository'
            )
            ->willReturn($settingValue);

        $this->assertEquals(
            $settingValue,
            $cachingSettingService->get(
                Provider::GITHUB,
                'owner',
                'repository',
                SettingKey::LINE_COMMENT_TYPE
            )
        );

        $this->assertEquals(
            $settingValue,
            $cachingSettingService->get(
                Provider::GITHUB,
                'owner',
                'repository',
                SettingKey::LINE_COMMENT_TYPE
            )
        );
    }

    #[DataProvider('settingValueDataProvider')]
    public function testSettingValueWithIdenticalValues(mixed $settingValue): void
    {
        $mockSettingService = $this->createMock(SettingServiceInterface::class);
        $mockSettingService->expects($this->once())
            ->method('set')
            ->with(
                Provider::GITHUB,
                'owner',
                'repository',
                SettingKey::LINE_COMMENT_TYPE,
                LineCommentType::ANNOTATION
            )
            ->willReturn(true);

        $cachingSettingService = new CachingSettingService($mockSettingService);

        $this->assertTrue(
            $cachingSettingService->set(
                Provider::GITHUB,
                'owner',
                'repository',
                SettingKey::LINE_COMMENT_TYPE,
                LineCommentType::ANNOTATION
            )
        );

        $this->assertTrue(
            $cachingSettingService->set(
                Provider::GITHUB,
                'owner',
                'repository',
                SettingKey::LINE_COMMENT_TYPE,
                LineCommentType::ANNOTATION
            )
        );
    }

    public function testDeletingValue(): void
    {
        $mockSettingService = $this->createMock(SettingServiceInterface::class);
        $mockSettingService->expects($this->once())
            ->method('delete')
            ->with(
                Provider::GITHUB,
                'owner',
                'repository',
                SettingKey::LINE_COMMENT_TYPE
            )
            ->willReturn(true);

        $cachingSettingService = new CachingSettingService($mockSettingService);

        $this->assertTrue(
            $cachingSettingService->delete(
                Provider::GITHUB,
                'owner',
                'repository',
                SettingKey::LINE_COMMENT_TYPE
            )
        );
    }

    public function testDeserializingValue(): void
    {
        $mockSettingService = $this->createMock(SettingServiceInterface::class);
        $mockSettingService->expects($this->once())
            ->method('deserialize')
            ->with(
                SettingKey::LINE_COMMENT_TYPE,
                LineCommentType::ANNOTATION->value
            )
            ->willReturn(LineCommentType::ANNOTATION);

        $cachingSettingService = new CachingSettingService($mockSettingService);

        $this->assertEquals(
            LineCommentType::ANNOTATION,
            $cachingSettingService->deserialize(
                SettingKey::LINE_COMMENT_TYPE,
                LineCommentType::ANNOTATION->value
            )
        );
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
