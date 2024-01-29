<?php

namespace Packages\Contracts\Provider;

/**
 * An interface for classes that should be aware of the provider they
 * are working with.
 *
 * For example, this is helpful for using tagged iterators that are services
 * for specific providers, and can be tagged using the provider enum.
 *
 * @see Provider
 */
interface ProviderAwareInterface
{
    public static function getProvider(): string;
}
