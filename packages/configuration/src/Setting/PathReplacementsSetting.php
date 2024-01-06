<?php

namespace Packages\Configuration\Setting;

use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use Override;
use Packages\Configuration\Client\DynamoDbClient;
use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Enum\SettingValueType;
use Packages\Configuration\Exception\InvalidSettingValueException;
use Packages\Configuration\Exception\SettingNotFoundException;
use Packages\Configuration\Exception\SettingRetrievalFailedException;
use Packages\Configuration\Model\PathReplacement;
use Packages\Contracts\Provider\Provider;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class PathReplacementsSetting implements SettingInterface
{
    private const array DEFAULT_VALUE = [];

    public function __construct(
        private readonly DynamoDbClient $dynamoDbClient,
        private readonly SerializerInterface&DenormalizerInterface $serializer,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * @return PathReplacement[]
     */
    #[Override]
    public function get(Provider $provider, string $owner, string $repository): array
    {
        try {
            $value = $this->dynamoDbClient->getSettingFromStore(
                $provider,
                $owner,
                $repository,
                SettingKey::PATH_REPLACEMENTS,
                SettingValueType::LIST
            );

            $value = $this->deserialize($value);

            $this->validate($value);

            return $value;
        } catch (
            ExceptionInterface |
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
     * @param PathReplacement[] $value
     *
     * @throws ExceptionInterface
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
            SettingKey::PATH_REPLACEMENTS,
            SettingValueType::LIST,
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
            SettingKey::PATH_REPLACEMENTS
        );
    }

    /**
     * @return PathReplacement[]
     * @throws ExceptionInterface
     */
    #[Override]
    public function deserialize(mixed $value): array
    {
        $pathReplacements = [];

        foreach ($value as $item) {
            if ($item instanceof PathReplacement) {
                $pathReplacements[] = $item;

                continue;
            }

            if ($item instanceof AttributeValue) {
                $map = $item->getM();

                $pathReplacements[] = new PathReplacement(
                    $map['before']->getS(),
                    $map['after']->getS()
                );
            } elseif (is_array($item)) {
                $pathReplacements[] = $this->serializer->denormalize(
                    $item,
                    PathReplacement::class,
                    'json'
                );
            }
        }

        return $pathReplacements;
    }

    #[Override]
    public static function getSettingKey(): string
    {
        return SettingKey::PATH_REPLACEMENTS->value;
    }

    #[Override]
    public function validate(mixed $value): void
    {
        if (!is_array($value)) {
            throw new InvalidSettingValueException(
                "Path replacements must be an array."
            );
        }

        $violations = $this->validator->validate(
            $value,
            [
                new Assert\All([
                    new Assert\Type(PathReplacement::class),
                ]),
            ]
        );

        if ($violations->count() > 0) {
            throw new InvalidSettingValueException(
                "Invalid value for setting: {$violations}"
            );
        }

        $violations = $this->validator->validate($value);

        if ($violations->count() > 0) {
            throw new InvalidSettingValueException(
                "Invalid path replacement value for setting: {$violations}"
            );
        }
    }

    /**
     * @param PathReplacement[] $pathReplacements
     * @return AttributeValue[]
     */
    private function serialize(array $pathReplacements): array
    {
        $attributeValues = [];

        foreach ($pathReplacements as $pathReplacement) {
            $before = ['S' => $pathReplacement->getBefore()];
            $after = $pathReplacement->getAfter() === null ?
                ['NULL' => true] :
                ['S' => $pathReplacement->getAfter()];

            $attributeValues[] = new AttributeValue(
                [
                    'M' => [
                        'before' => new AttributeValue($before),
                        'after' => new AttributeValue($after),
                    ],
                ]
            );
        }

        return $attributeValues;
    }
}
