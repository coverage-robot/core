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

    $defaultConfig($rectorConfig);
};
