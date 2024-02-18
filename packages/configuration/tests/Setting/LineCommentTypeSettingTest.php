<?php

namespace Packages\Configuration\Tests\Setting;

use Packages\Configuration\Client\DynamoDbClientInterface;
use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Enum\SettingValueType;
use Packages\Configuration\Exception\InvalidSettingValueException;
use Packages\Configuration\Model\LineCommentType;
use Packages\Configuration\Setting\LineCommentTypeSetting;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class LineCommentTypeSettingTest extends TestCase
{
    #[DataProvider('settingValueDataProvider')]
    public function testSettingLineCommentTypeSetting(string $settingValue): void
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
                $settingValue
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
    public function testGettingLineAnnotationSetting(string $settingValue): void
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
            ->willReturn($settingValue);

        $lineCommentTypeSetting = new LineCommentTypeSetting(
            $mockDynamoDbClient
        );

        $this->assertEquals(
            LineCommentType::from($settingValue),
            $lineCommentTypeSetting->get(
                Provider::GITHUB,
                'owner',
                'repository'
            )
        );
    }

    #[DataProvider('validatingValuesDataProvider')]
    public function testValidatingLineAnnotationValue(mixed $settingValue, bool $expectedValid): void
    {
        $lineCommentTypeSetting = new LineCommentTypeSetting(
            $this->createMock(DynamoDbClientInterface::class)
        );

        if (!$expectedValid) {
            $this->expectException(InvalidSettingValueException::class);
        } else {
            $this->expectNotToPerformAssertions();
        }

        $lineCommentTypeSetting->validate($settingValue);
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
            fn (LineCommentType $lineCommentType): string => $lineCommentType->value,
            LineCommentType::cases()
        );
        return array_combine(
            $cases,
            array_map(
                fn(string $case) => [$case],
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
