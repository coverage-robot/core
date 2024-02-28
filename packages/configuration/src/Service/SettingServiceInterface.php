<?php

namespace Packages\Configuration\Service;

use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Exception\InvalidSettingValueException;
use Packages\Contracts\Provider\Provider;

interface SettingServiceInterface
{
    /**
     * Get a setting's value, for a specific repository, from the configuration store.
     */
    public function get(
        Provider $provider,
        string $owner,
        string $repository,
        SettingKey $key
    ): mixed;

    /**
     * Set the state of a setting for a particular repository in the configuration store.
     * @throws InvalidSettingValueException
     */
    public function set(
        Provider $provider,
        string $owner,
        string $repository,
        SettingKey $key,
        mixed $value
    ): bool;

    /**
     * Delete a setting for a particular repository in the configuration store.
     */
    public function delete(
        Provider $provider,
        string $owner,
        string $repository,
        SettingKey $key
    ): bool;

    /**
     * Validate the value of a setting.
     *
     * @throws InvalidSettingValueException
     */
    public function deserialize(
        SettingKey $key,
        mixed $value
    ): mixed;
}
