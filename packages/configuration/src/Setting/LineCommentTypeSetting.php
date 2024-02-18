<?php

namespace Packages\Configuration\Setting;

use Override;
use Packages\Configuration\Client\DynamoDbClientInterface;
use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Enum\SettingValueType;
use Packages\Configuration\Exception\InvalidSettingValueException;
use Packages\Configuration\Exception\SettingNotFoundException;
use Packages\Configuration\Exception\SettingRetrievalFailedException;
use Packages\Configuration\Model\LineCommentType;
use Packages\Contracts\Provider\Provider;

final class LineCommentTypeSetting implements SettingInterface
{
    private const LineCommentType DEFAULT_VALUE = LineCommentType::REVIEW_COMMENT;

    public function __construct(
        private readonly DynamoDbClientInterface $dynamoDbClient
    ) {
    }

    #[Override]
    public function get(Provider $provider, string $owner, string $repository): LineCommentType
    {
        try {
            $value = $this->dynamoDbClient->getSettingFromStore(
                $provider,
                $owner,
                $repository,
                SettingKey::LINE_COMMENT_TYPE,
                SettingValueType::STRING
            );

            $this->validate($value);

            return $this->deserialize($value);
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
        return $this->dynamoDbClient->setSettingInStore(
            $provider,
            $owner,
            $repository,
            SettingKey::LINE_COMMENT_TYPE,
            SettingValueType::STRING,
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
            SettingKey::LINE_COMMENT_TYPE
        );
    }

    /**
     * @throws InvalidSettingValueException
     */
    #[Override]
    public function deserialize(mixed $value): mixed
    {
        return LineCommentType::from($value);
    }

    #[Override]
    public function validate(mixed $value): void
    {
        if ($value instanceof LineCommentType) {
            return;
        }

        if (!is_string($value) || !LineCommentType::tryFrom($value) instanceof LineCommentType) {
            throw new InvalidSettingValueException(
                'The value for the line comment type setting must be a valid enum value.'
            );
        }
    }

    #[Override]
    public static function getSettingKey(): string
    {
        return SettingKey::LINE_COMMENT_TYPE->value;
    }
}
