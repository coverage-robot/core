<?php

use Rector\Config\RectorConfig;
use Rector\PHPUnit\PHPUnit60\Rector\MethodCall\GetMockBuilderGetMockToCreateMockRector;
use Rector\PHPUnit\Set\PHPUnitLevelSetList;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Symfony\Set\SymfonyLevelSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->sets([
        /**
         * Basic code quality rules - more specific rules are already
         * enforced by Psalm and PHP_CS
         */
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
        SetList::DEAD_CODE,

        /**
         * ALl services are already on PHP 8.3, so this is safe to promote
         * across services.
         */
        LevelSetList::UP_TO_PHP_83,

        /**
         * All services are already on PHPUnit 10, so this is safe to promote
         * across services.
         */
        PHPUnitLevelSetList::UP_TO_PHPUNIT_100,

        /**
         * All services are already on Symfony 6.4+, so this is safe to promote
         * across services (no Symfony 7 rules are available yet).
         */
        SymfonyLevelSetList::UP_TO_SYMFONY_64
    ]);

    $rectorConfig->skip([
        /**
         * Ignore as there are genuine use cases for mock builders over `createMock`,
         * because that method is protected.
         *
         * For example, static helpers for creating mocks:
         * ```php
         * private static function getMockUpload(TestCase $test): Upload
         * {
         *      return $test->getMockBuilder(Upload::class)
         *          ->disableOriginalConstructor()
         *          ->getMock();
         * }
         * ```
         */
        GetMockBuilderGetMockToCreateMockRector::class
    ]);
};
