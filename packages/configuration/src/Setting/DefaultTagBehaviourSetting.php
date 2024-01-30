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
use Packages\Configuration\Model\DefaultTagBehaviour;
use Packages\Contracts\Provider\Provider;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class DefaultTagBehaviourSetting implements SettingInterface
{
    private DefaultTagBehaviour $default;

    public function __construct(
        private readonly DynamoDbClientInterface $dynamoDbClient,
        private readonly SerializerInterface&DenormalizerInterface $serializer,
        private readonly ValidatorInterface $validator,
    ) {
        $this->default = new DefaultTagBehaviour(
            carryforward: true
        );
    }

    #[Override]
    public function get(Provider $provider, string $owner, string $repository): DefaultTagBehaviour
    {
        try {
            $value = $this->dynamoDbClient->getSettingFromStore(
                $provider,
                $owner,
                $repository,
                SettingKey::from(self::getSettingKey()),
                SettingValueType::MAP
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
            return $this->default;
        }
    }

    /**
     * @param DefaultTagBehaviour $value
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
            SettingKey::from(self::getSettingKey()),
            SettingValueType::MAP,
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
            SettingKey::from(self::getSettingKey())
        );
    }

    /**
     * @throws ExceptionInterface
     * @throws InvalidSettingValueException
     */
    #[Override]
    public function deserialize(mixed $value): DefaultTagBehaviour
    {
        if ($value instanceof DefaultTagBehaviour) {
            return $value;
        }

        if ($value instanceof AttributeValue) {
            $map = $value->getM();

            return new DefaultTagBehaviour(
                $map['carryforward']->getBool()
            );
        }

        if (is_array($value)) {
            return $this->serializer->denormalize(
                $value,
                DefaultTagBehaviour::class,
                'json'
            );
        }

        throw new InvalidSettingValueException(
            'Invalid value for setting: ' . $value
        );
    }

    #[Override]
    public static function getSettingKey(): string
    {
        return SettingKey::DEFAULT_TAG_BEHAVIOUR->value;
    }

    #[Override]
    public function validate(mixed $value): void
    {
        $violations = $this->validator->validate(
            $value,
            [
                new Assert\NotNull(),
                new Assert\Type(DefaultTagBehaviour::class)
            ]
        );

        if ($violations->count() > 0) {
            throw new InvalidSettingValueException(
                'Invalid value for setting: ' . $violations
            );
        }

        $violations = $this->validator->validate($value);

        if ($violations->count() > 0) {
            throw new InvalidSettingValueException(
                'Invalid default tag behaviour value for setting: ' . $violations
            );
        }
    }

    private function serialize(DefaultTagBehaviour $defaultTagBehaviour): array
    {
        return [
            'carryforward' => new AttributeValue(
                [
                    SettingValueType::BOOLEAN->value => $defaultTagBehaviour->getCarryforward()
                ]
            ),
        ];
    }
}
