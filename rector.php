<?php

use Rector\Config\RectorConfig;
use Rector\Nette\Set\NetteSetList;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->sets([
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,

        SetList::DEAD_CODE,

        LevelSetList::UP_TO_PHP_83
    ]);

    $rectorConfig->skip([
        // Ignore as Psalm currently throws up an exception about the Override
        // attribute: https://github.com/vimeo/psalm/issues/10404
        AddOverrideAttributeToOverriddenMethodsRector::class
    ]);
};
