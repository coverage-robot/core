<?php

use Rector\Config\RectorConfig;
use Rector\Naming\Rector\Assign\RenameVariableToMatchMethodCallReturnTypeRector;
use Rector\Naming\Rector\Class_\RenamePropertyToMatchTypeRector;
use Rector\Naming\Rector\ClassMethod\RenameParamToMatchTypeRector;
use Rector\Naming\Rector\ClassMethod\RenameVariableToMatchNewTypeRector;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;

return RectorConfig::configure()
    ->withPreparedSets(
        deadCode: true,
        codingStyle: true,
        typeDeclarations: true,
        typeDeclarationDocblocks: true,
        privatization: true,
        naming: true,
        instanceOf: true,
        earlyReturn: true,
        carbon: false, // No services use Carbon
        rectorPreset: true,
        phpunitCodeQuality: true,
        doctrineCodeQuality: false, // No service use Doctrine
        symfonyCodeQuality: true,
        symfonyConfigs: true
    )
    ->withComposerBased(
        twig: true,
        doctrine: false, // No service use Doctrine
        phpunit: true,
        symfony: true,
        netteUtils: false, // No service use Nette
        laravel: false // No service use Laravel
    )
    ->withImportNames(
        importNames: true,
        importDocBlockNames: true,
        importShortClasses: true,
        removeUnusedImports: true,
    )
    ->withPhpSets()
    ->withAttributesSets()
    ->withTreatClassesAsFinal()
    ->withRules([
            /**
             * Make sure all overridden methods have the `#[Override]` attribute.
             */
            AddOverrideAttributeToOverriddenMethodsRector::class,
        ])
    ->withSkip([
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
