<?php

namespace Packages\Configuration\Tests\Setting;

use Packages\Configuration\Client\DynamoDbClientInterface;
use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Enum\SettingValueType;
use Packages\Configuration\Model\LineCommentType;
use Packages\Configuration\Setting\LineCommentTypeSetting;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class LineCommentTypeSettingTest extends TestCase
{
    #[DataProvider('settingValueDataProvider')]
    public function testSettingLineCommentTypeSetting(LineCommentType $settingValue): void
    {
        $mockDynamoDbClient = $this->createMock(DynamoDbClientInterface::class);
        $mockDynamoDbClient->expects($this->once())
            ->method('setSettingInStore')
            ->with(
                Provider::GITHUB,
                'owner',
                'repository',
                SettingKey::LINE_COMMENT_TYPE,
                SettingValueType::STRING,
                $settingValue->value
            )
            ->willReturn(true);

        $lineCommentTypeSetting = new LineCommentTypeSetting(
            $mockDynamoDbClient
        );

        $this->assertTrue(
            $lineCommentTypeSetting->set(
                Provider::GITHUB,
                'owner',
                'repository',
                $settingValue
            )
        );
    }

    #[DataProvider('settingValueDataProvider')]
    public function testGettingLineAnnotationSetting(LineCommentType $settingValue): void
    {
        $mockDynamoDbClient = $this->createMock(DynamoDbClientInterface::class);
        $mockDynamoDbClient->expects($this->once())
            ->method('getSettingFromStore')
            ->with(
                Provider::GITHUB,
                'owner',
                'repository',
                SettingKey::LINE_COMMENT_TYPE,
                SettingValueType::STRING
            )
            ->willReturn($settingValue->value);

        $lineCommentTypeSetting = new LineCommentTypeSetting(
            $mockDynamoDbClient
        );

        $this->assertEquals(
            $settingValue,
            $lineCommentTypeSetting->get(
                Provider::GITHUB,
                'owner',
                'repository'
            )
        );
    }

    public function testSettingKey(): void
    {
        $this->assertEquals(
            SettingKey::LINE_COMMENT_TYPE->value,
            LineCommentTypeSetting::getSettingKey()
        );
    }

    public static function settingValueDataProvider(): array
    {
        $cases = array_map(
            static fn(LineCommentType $lineCommentType): LineCommentType => $lineCommentType,
            LineCommentType::cases()
        );
        return array_combine(
            array_map(
                static fn(LineCommentType $case): string => $case->value,
                $cases
            ),
            array_map(
                static fn(LineCommentType $case): array => [$case],
                $cases
            )
        );
    }

    public static function validatingValuesDataProvider(): array
    {
        return [
            'True' => [
                true,
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
            'Array' => [
                [],
                false
            ],
            'Object' => [
                new stdClass(),
                false
            ],
            'LineCommentType::REVIEW_COMMENT' => [
                LineCommentType::REVIEW_COMMENT->value,
                true
            ],
            'LineCommentType::HIDDEN' => [
                LineCommentType::HIDDEN->value,
                true
            ],
            'LineCommentType::ANNOTATION' => [
                LineCommentType::ANNOTATION->value,
                true
            ]
        ];
    }
}
