<?php

namespace Packages\Configuration\Setting;

use Packages\Configuration\Exception\InvalidSettingValueException;
use Packages\Contracts\Provider\Provider;

interface SettingInterface
{
    /**
     * Get the configuration setting's value from the configuration store.
     *
     * @throws InvalidSettingValueException
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
        $value
    ): bool;

    /**
     * Delete the configuration setting from the configuration store.
     */
    public function delete(
        Provider $provider,
        string $owner,
        string $repository
    ): bool;

    /**
     * Deserialize the configuration setting's value from the a configuration file,
     * or the configuration store, into a manipulate format (i.e. model, primitive, etc)
     *
     * @throws InvalidSettingValueException
     */
    public function deserialize(mixed $value): mixed;

    /**
     * Serialize the configuration setting's value from a manipulate format (i.e. model, primitive, etc)
     * into a format that can be stored in the configuration store.
     *
     * @throws InvalidSettingValueException
     */
    public function serialize(mixed $value): mixed;

    /**
     * The key of the configuration value.
     */
    public static function getSettingKey(): string;
}
