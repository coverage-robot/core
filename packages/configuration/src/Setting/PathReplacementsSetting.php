<?php

namespace Packages\Configuration\Setting;

use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use Override;
use Packages\Configuration\Client\DynamoDbClientInterface;
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

final class PathReplacementsSetting implements SettingInterface
{
    private const array DEFAULT_VALUE = [];

    public function __construct(
        private readonly DynamoDbClientInterface $dynamoDbClient,
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
            return $this->deserialize(
                $this->dynamoDbClient->getSettingFromStore(
                    $provider,
                    $owner,
                    $repository,
                    SettingKey::PATH_REPLACEMENTS,
                    SettingValueType::LIST
                )
            );
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
     * @throws InvalidSettingValueException
     */
    #[Override]
    public function deserialize(mixed $value): array
    {
        $pathReplacements = [];

        foreach ((array) $value as $item) {
            $pathReplacements[] = match (true) {
                $item instanceof AttributeValue => new PathReplacement(
                    $item->getM()['before']->getS(),
                    $item->getM()['after']->getS()
                ),
                is_array($item) => $this->serializer->denormalize(
                    $item,
                    PathReplacement::class,
                    'json'
                ),
                default => $item,
            };
        }

        return $this->validate($pathReplacements);
    }

    #[Override]
    public static function getSettingKey(): string
    {
        return SettingKey::PATH_REPLACEMENTS->value;
    }

    /**
     * @param PathReplacement[] $value
     * @return AttributeValue[]
     *
     * @throws InvalidSettingValueException
     */
    #[Override]
    public function serialize(mixed $value): array
    {
        $attributeValues = [];

        foreach ($this->validate($value) as $pathReplacement) {
            $before = [SettingValueType::STRING->value => $pathReplacement->getBefore()];
            $after = $pathReplacement->getAfter() === null ?
                [SettingValueType::NULL->value => true] :
                [SettingValueType::STRING->value => $pathReplacement->getAfter()];

            $attributeValues[] = new AttributeValue(
                [
                    SettingValueType::MAP->value => [
                        'before' => new AttributeValue($before),
                        'after' => new AttributeValue($after),
                    ],
                ]
            );
        }

        return $attributeValues;
    }

    /**
     * @return PathReplacement[]
     * @throws InvalidSettingValueException
     */
    private function validate(mixed $value): array
    {
        $violations = $this->validator->validate(
            $value,
            [
                new Assert\Valid(),
                new Assert\All([
                    new Assert\Type(PathReplacement::class),
                ]),
            ]
        );

        if ($violations->count() > 0) {
            throw new InvalidSettingValueException('Invalid value for setting: ' . $violations);
        }

        /** @var PathReplacement[] $value */
        return $value;
    }
}
