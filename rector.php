<?php

use Rector\Config\RectorConfig;
use Rector\Naming\Rector\Assign\RenameVariableToMatchMethodCallReturnTypeRector;
use Rector\Naming\Rector\Class_\RenamePropertyToMatchTypeRector;
use Rector\Naming\Rector\ClassMethod\RenameParamToMatchTypeRector;
use Rector\Naming\Rector\ClassMethod\RenameVariableToMatchNewTypeRector;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->sets([
        /**
         * Basic code quality rules - more specific rules are already enforced by Psalm
         * and PHP_CS.
         */
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
        SetList::DEAD_CODE,
        SetList::NAMING,
        SetList::TYPE_DECLARATION,
        SetList::PRIVATIZATION,
        SetList::EARLY_RETURN,
        SetList::INSTANCEOF,
    ]);

    /**
     * Make sure all overridden methods have the `#[Override]` attribute.
     */
    $rectorConfig->rule(AddOverrideAttributeToOverriddenMethodsRector::class);

    $rectorConfig->skip([
        /**
         * Ignore as, although this is a good pattern in most cases, theres genuine use cases for
         * a variable name which _doesn't_ match the type being instantiated.
         *
         * For example, creating instances of DateTimeImmutables loses a lot of context when
         * the variable is renamed to `$dateTimeImmutable`:
         * ```php
         * $dateTimeImmutable = new DateTimeImmutable();
         * ```
         * or:
         * ```php
         * function method(DateTimeImmutable $dateTimeImmutable): void
         * ```
         */
        RenameVariableToMatchNewTypeRector::class,
        RenameParamToMatchTypeRector::class,
        RenameVariableToMatchMethodCallReturnTypeRector::class,
        RenamePropertyToMatchTypeRector::class
    ]);
};
