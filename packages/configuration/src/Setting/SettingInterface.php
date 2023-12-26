<?php

namespace Packages\Configuration\Setting;

use Packages\Contracts\Provider\Provider;

interface SettingInterface
{
    /**
     * Get the configuration setting's value from the configuration store.
     */
    public function get(
        Provider $provider,
        string $owner,
        string $repository
    ): mixed;

    /**
     * Set the configuration setting from a Yaml file.
     */
    public function set(
        Provider $provider,
        string $owner,
        string $repository,
        mixed $value
    ): bool;

    /**
     * Validate the configuration setting's value.
     */
    public function validate(mixed $value): bool;

    /**
     * The key of the configuration value.
     */
    public static function getSettingKey(): string;
}
