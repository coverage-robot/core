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
            return $this->deserialize(
                $this->dynamoDbClient->getSettingFromStore(
                    $provider,
                    $owner,
                    $repository,
                    SettingKey::LINE_COMMENT_TYPE,
                    SettingValueType::STRING
                )
            );
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
     * @throws InvalidSettingValueException
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
            $this->serialize($value)
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
    public function deserialize(mixed $value): LineCommentType
    {
        $value = LineCommentType::tryFrom((string) $value);

        return $this->validate($value);
    }

    /**
     * @throws InvalidSettingValueException
     */
    #[Override]
    public function serialize(mixed $value): string
    {
        return $this->validate($value)
            ->value;
    }

    #[Override]
    public static function getSettingKey(): string
    {
        return SettingKey::LINE_COMMENT_TYPE->value;
    }

    private function validate(mixed $value): LineCommentType
    {
        if ($value instanceof LineCommentType) {
            return $value;
        }

        throw new InvalidSettingValueException(
            'The value for the line comment type setting must be a valid enum value.'
        );
    }
}
