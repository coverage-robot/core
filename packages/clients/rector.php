<?php

use Rector\Config\RectorConfig;
use Rector\Privatization\Rector\Class_\FinalizeClassesWithoutChildrenRector;

$defaultConfig = require __DIR__ . '/../../rector.php';

return static function (RectorConfig $rectorConfig) use ($defaultConfig): void {
    $rectorConfig->paths(
        [
            __DIR__ . '/src/*',
            __DIR__ . '/tests/*'
        ]
    );

    $rectorConfig->skip(
        [
            FinalizeClassesWithoutChildrenRector::class => [
                /**
                 * Ignoring for the time being as the GitHub clients could do with a bit of a refactor
                 */
                __DIR__ . '/src/Client/GithubAppClient.php',
                __DIR__ . '/src/Generator/JwtGenerator.php',
            ],
        ]
    );

    $defaultConfig($rectorConfig);
};
