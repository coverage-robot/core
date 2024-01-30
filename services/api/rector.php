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
                 * Ignoring as non-final repositories allows for easier testing (while theres
                 * no mock database instance in CI) without needing a new interface for every
                 * repository.
                 */
                __DIR__ . '/src/Repository',
            ],
        ]
    );

    $defaultConfig($rectorConfig);
};
