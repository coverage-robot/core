<?php

namespace Packages\Configuration\Tests\Setting;

use Packages\Configuration\Client\DynamoDbClient;
use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Exception\InvalidSettingValueException;
use Packages\Configuration\Model\PathReplacement;
use Packages\Configuration\Setting\PathReplacementsSetting;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validation;

class PathReplacementsSettingTest extends TestCase
{
    #[DataProvider('validatingValuesDataProvider')]
    public function testValidatingPathReplacementsValue(mixed $settingValue, bool $expectedValid): void
    {
        $lineAnnotationSetting = new PathReplacementsSetting(
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

        $lineAnnotationSetting->validate($settingValue);
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
