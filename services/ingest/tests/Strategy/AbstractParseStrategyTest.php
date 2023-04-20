<?php

namespace App\Tests\Strategy;

use PHPUnit\Framework\TestCase;

abstract class AbstractParseStrategyTest extends TestCase
{
    protected static function parseFixturesDataProvider(string $path, string $fileExtension): array
    {
        return array_reduce(
            glob(sprintf("%s/*.%s", $path, $fileExtension)),
            static fn (array $fixtures, string $path) =>
                [
                    ...$fixtures,
                    basename($path, "." . $fileExtension) => [
                        file_get_contents($path),
                        true,
                        json_decode(
                            file_get_contents(
                                substr($path, 0, strlen($path) - strlen($fileExtension)) . "json"
                            ),
                            true
                        ),
                    ]
                ],
            []
        );
    }
}
