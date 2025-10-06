<?php

declare(strict_types=1);

namespace Packages\Configuration\Client;

use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Enum\SettingValueType;
use Packages\Contracts\Provider\Provider;

interface DynamoDbClientInterface
{
    /**
     * The primary key for the configuration store.
     *
     * This is the grouping for the settings, which is the provider, owner and repository.
     */
    public const string REPOSITORY_IDENTIFIER_COLUMN = 'repositoryIdentifier';

    /**
     * The range key for the configuration store.
     *
     * This is the unique setting identifier (in dot notation).
     */
    public const string SETTING_KEY_COLUMN = 'settingKey';

    /**
     * The value column for the configuration store.
     *
     * This is the value of the setting for a repository.
     */
    public const string VALUE_COLUMN = 'value';

    public function getSettingFromStore(
        Provider $provider,
        string $owner,
        string $repository,
        SettingKey $key,
        SettingValueType $type
    ): mixed;

    public function setSettingInStore(
        Provider $provider,
        string $owner,
        string $repository,
        SettingKey $key,
        SettingValueType $type,
        mixed $value
    ): bool;

    public function deleteSettingFromStore(
        Provider $provider,
        string $owner,
        string $repository,
        SettingKey $key
    ): bool;
}
