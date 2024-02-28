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
use Packages\Configuration\Model\IndividualTagBehaviour;
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
final class IndividualTagBehavioursSetting implements SettingInterface
{
    private const array DEFAULT_VALUE = [];

    public function __construct(
        private readonly DynamoDbClientInterface $dynamoDbClient,
        private readonly SerializerInterface&DenormalizerInterface $serializer,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * @return IndividualTagBehaviour[]
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
                    SettingKey::INDIVIDUAL_TAG_BEHAVIOURS,
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
     * @param IndividualTagBehaviour[] $value
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
            SettingKey::INDIVIDUAL_TAG_BEHAVIOURS,
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
            SettingKey::INDIVIDUAL_TAG_BEHAVIOURS
        );
    }

    /**
     * @return IndividualTagBehaviour[]
     * @throws ExceptionInterface
     * @throws InvalidSettingValueException
     */
    #[Override]
    public function deserialize(mixed $value): array
    {
        $behaviours = [];

        foreach ($value as $item) {
            if ($item instanceof IndividualTagBehaviour) {
                $behaviours[] = $item;

                continue;
            }

            if ($item instanceof AttributeValue) {
                $map = $item->getM();

                $behaviours[] = new IndividualTagBehaviour(
                    $map['name']->getS(),
                    $map['carryforward']->getBool()
                );
            } elseif (is_array($item)) {
                $behaviours[] = $this->serializer->denormalize(
                    $item,
                    IndividualTagBehaviour::class,
                    'json'
                );
            }
        }

        return $this->validate($behaviours);
    }

    #[Override]
    public static function getSettingKey(): string
    {
        return SettingKey::INDIVIDUAL_TAG_BEHAVIOURS->value;
    }

    /**
     * @param IndividualTagBehaviour[] $value
     * @return AttributeValue[]
     * @throws InvalidSettingValueException
     */
    #[Override]
    public function serialize(mixed $value): array
    {
        $attributeValues = [];

        foreach ($this->validate($value) as $individualTagBehaviour) {
            $attributeValues[] = new AttributeValue(
                [
                    SettingValueType::MAP->value => [
                        'name' => new AttributeValue(
                            [
                                SettingValueType::STRING->value => $individualTagBehaviour->getName()
                            ]
                        ),
                        'carryforward' => new AttributeValue(
                            [
                                SettingValueType::BOOLEAN->value => $individualTagBehaviour->getCarryforward()
                            ]
                        ),
                    ],
                ]
            );
        }

        return $attributeValues;
    }

    /**
     * @return IndividualTagBehaviour[]
     * @throws InvalidSettingValueException
     */
    private function validate(mixed $value): array
    {
        $violations = $this->validator->validate(
            $value,
            [
                new Assert\Valid(),
                new Assert\All([
                    new Assert\Type(IndividualTagBehaviour::class)
                ]),
            ]
        );

        if ($violations->count() > 0) {
            throw new InvalidSettingValueException('Invalid value for setting: ' . $violations);
        }

        /** @var IndividualTagBehaviour[] $value */
        return $value;
    }
}
