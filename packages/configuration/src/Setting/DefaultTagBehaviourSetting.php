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

/**
 * @internal
 *
 * @see TagBehaviourService
 */
final class DefaultTagBehaviourSetting implements SettingInterface
{
    private DefaultTagBehaviour $default;

    public function __construct(
        private readonly DynamoDbClientInterface $dynamoDbClient,
        private readonly SerializerInterface&DenormalizerInterface $serializer,
        private readonly ValidatorInterface $validator,
    ) {
        $this->default = new DefaultTagBehaviour(carryforward: true);
    }

    #[Override]
    public function get(Provider $provider, string $owner, string $repository): DefaultTagBehaviour
    {
        try {
            return $this->deserialize(
                $this->dynamoDbClient->getSettingFromStore(
                    $provider,
                    $owner,
                    $repository,
                    SettingKey::DEFAULT_TAG_BEHAVIOUR,
                    SettingValueType::MAP
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
            return $this->default;
        }
    }

    /**
     * @param DefaultTagBehaviour $value
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
        $value = match (true) {
            $value instanceof AttributeValue => new DefaultTagBehaviour(
                $value->getM()['carryforward']->getBool()
            ),
            is_array($value) => $this->serializer->denormalize(
                $value,
                DefaultTagBehaviour::class,
                'json'
            ),
            default => $value
        };

        return $this->validate($value);
    }

    #[Override]
    public static function getSettingKey(): string
    {
        return SettingKey::DEFAULT_TAG_BEHAVIOUR->value;
    }

    /**
     * @throws InvalidSettingValueException
     */
    #[Override]
    public function serialize(mixed $value): array
    {
        $this->validate($value);

        return [
            'carryforward' => new AttributeValue(
                [
                    SettingValueType::BOOLEAN->value => $value->getCarryforward()
                ]
            ),
        ];
    }

    /**
     * @throws InvalidSettingValueException
     */
    private function validate(mixed $value): DefaultTagBehaviour
    {
        $violations = $this->validator->validate(
            $value,
            [
                new Assert\Valid(),
                new Assert\Type(DefaultTagBehaviour::class)
            ]
        );

        if ($violations->count() > 0) {
            throw new InvalidSettingValueException('Invalid value for setting: ' . $violations);
        }

        /** @var DefaultTagBehaviour $value */
        return $value;
    }
}
