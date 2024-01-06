<?php

namespace Packages\Configuration\Setting;

use Override;
use Packages\Configuration\Client\DynamoDbClient;
use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Enum\SettingValueType;
use Packages\Configuration\Exception\InvalidSettingValueException;
use Packages\Configuration\Exception\SettingNotFoundException;
use Packages\Configuration\Exception\SettingRetrievalFailedException;
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

            $this->validate($value);

            return $value;
        } catch (
            SettingNotFoundException |
            SettingRetrievalFailedException |
            InvalidSettingValueException
        ) {
            // Either the setting was not set (entirely possible) or the retrieval failed,
            // in either case, fail safe and return the default value.
            return self::DEFAULT_VALUE;
        }
    }

    /**
     * @param bool $value
     */
    #[Override]
    public function set(
        Provider $provider,
        string $owner,
        string $repository,
        mixed $value
    ): bool {
        $this->validate($value);

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

    /**
     * @throws InvalidSettingValueException
     */
    #[Override]
    public function deserialize(mixed $value): mixed
    {
        /**
         * Theres no particular deserialization required for line annotations. At
         * this point, its not yet confirmed if the value is a boolean, but that will
         * be handled at the validation step.
         *
         * @see self::validate()
         */
        return $value;
    }

    #[Override]
    public function validate(mixed $value): void
    {
        if (!is_bool($value)) {
            throw new InvalidSettingValueException(
                'The value for the line annotation setting must be a boolean.'
            );
        }
    }

    #[Override]
    public static function getSettingKey(): string
    {
        return SettingKey::LINE_ANNOTATION->value;
    }
}
