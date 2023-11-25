<?php

use Rector\Config\RectorConfig;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;

$defaultConfig = require __DIR__ . '/../../rector.php';

return static function (RectorConfig $rectorConfig) use ($defaultConfig): void {
    $rectorConfig->paths(
        [
            __DIR__ . '/src/*',
            __DIR__ . '/tests/*'
        ]
    );

    $rectorConfig->skip([
        /**
         * Ignore as the API is currently on PHP 8.2 and this is a PHP 8.3 rule
         *
         * @see infrastructure/variables.tf
         */
        AddOverrideAttributeToOverriddenMethodsRector::class
    ]);

    $defaultConfig($rectorConfig);
};
