<?php

namespace App\Extension\Function;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.template_available')]
interface TwigFunctionInterface
{
    /**
     * Get the name of the function which should be exposed in the
     * templating environment.
     */
    public static function getFunctionName(): string;

    /**
     * Get the options to configure how the function should be exposed when
     * available in the templating environment.
     *
     * For example, the `needs_context` option can be used to pass the current
     * context to the function when it is called.
     */
    public static function getOptions(): array;
}
