<?php

namespace Packages\Configuration\Setting;

use Override;
use Packages\Configuration\Client\DynamoDbClient;
use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Enum\SettingValueType;
use Packages\Configuration\Exception\SettingNotFoundException;
use Packages\Configuration\Exception\SettingRetrievalFailed;
use Packages\Contracts\Provider\Provider;

class LineAnnotationSetting implements SettingInterface
{
    private const true DEFAULT_VALUE = true;

    public function __construct(
        private readonly DynamoDbClient $dynamoDbClient
    ) {
    }

    #[Override]
    public function get(Provider $provider, string $owner, string $repository): bool
    {
        try {
            $value = $this->dynamoDbClient->getSettingFromStore(
                $provider,
                $owner,
                $repository,
                SettingKey::LINE_ANNOTATION,
                SettingValueType::BOOLEAN
            );

            if (!$this->validate($value)) {
                // The store has an invalid value, so return the default.
                return self::DEFAULT_VALUE;
            }

            return $value;
        } catch (SettingNotFoundException | SettingRetrievalFailed) {
            // Either the setting was not set (entirely possible) or the retrieval failed,
            // in either case, fail safe and return the default value.
            return self::DEFAULT_VALUE;
        }
    }

    #[Override]
    public function set(
        Provider $provider,
        string $owner,
        string $repository,
        mixed $value
    ): bool {
        if (!$this->validate($value)) {
            return false;
        }

        return $this->dynamoDbClient->setSettingInStore(
            $provider,
            $owner,
            $repository,
            SettingKey::LINE_ANNOTATION,
            SettingValueType::BOOLEAN,
            $value
        );
    }

    #[Override]
    public function delete(
        Provider $provider,
        string $owner,
        string $repository
    ): bool {
        return $this->dynamoDbClient->deleteSettingFromStore(
            $provider,
            $owner,
            $repository,
            SettingKey::LINE_ANNOTATION
        );
    }

    #[Override]
    public function validate(mixed $value): bool
    {
        return is_bool($value);
    }

    #[Override]
    public static function getSettingKey(): string
    {
        return SettingKey::LINE_ANNOTATION->value;
    }
}
