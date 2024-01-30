<?php

namespace Packages\Local\Service;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use InvalidArgumentException;
use Override;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Model\EventInterface;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class ManualInputEventBuilder implements EventBuilderInterface
{
    public function __construct(
        private readonly PropertyInfoExtractor $propertyInfoExtractor,
        private readonly ClassDiscriminatorFromClassMetadata $classDiscriminatorFromClassMetadata,
        private readonly SerializerInterface&DenormalizerInterface $serializer,
    ) {
    }

    #[Override]
    public static function supports(InputInterface $input, Event $event): bool
    {
        return true;
    }

    #[Override]
    public static function getPriority(): int
    {
        /**
         * Set a low priority so that all other builders are evaluated first, as this builder
         * will act as a 'catch all' fallback, which can handle any event, regardless of inputs.
         */
        return -1000;
    }

    #[Override]
    public function build(
        InputInterface $input,
        OutputInterface $output,
        ?HelperSet $helperSet,
        Event $event
    ): EventInterface {
        $payload = [];

        $discriminatorMap = $this->classDiscriminatorFromClassMetadata->getMappingForMappedObject(
            EventInterface::class
        );

        $eventClass = $discriminatorMap->getClassForType($event->value);

        $properties = $this->propertyInfoExtractor->getProperties($eventClass);

        $helper = $helperSet->get('question');
        foreach ($properties as $index => $property) {
            if ($property === 'type') {
                // We can ignore the type property as that's for discrimination during denormalization
                continue;
            }

            $types = $this->propertyInfoExtractor->getTypes($eventClass, $property);

            $question = new Question(
                sprintf(
                    '<question>(%s/%s) Enter value for "%s" (type: %s):</question>',
                    $index + 1,
                    count($properties),
                    $property,
                    implode(
                        "|",
                        array_map(
                            static fn (Type $type): string => $type->getClassName() ?? $type->getBuiltinType(),
                            $types
                        )
                    )
                )
            );
            $this->setPropertyQuestionConstraintsBasedOnTypes($question, $types);

            $payload[$property] = $helper->ask($input, $output, $question);
        }

        return $this->serializer->denormalize(
            $payload,
            $eventClass
        );
    }

    /**
     * @param Type[] $types
     */
    private function setPropertyQuestionConstraintsBasedOnTypes(Question $question, array $types): void
    {
        switch ($types[0]->getClassName() ?? $types[0]->getBuiltinType()) {
            case 'array':
                $question->setNormalizer(static fn(?string $value): ?array => $value !== null ? explode(',', $value) : $value);
                break;
            case Provider::class:
                $question->setValidator($this->getEnumValidatorCallback(Provider::class));
                $question->setAutocompleterValues($this->getEnumAutocompleteValues(Provider::class));
                break;
            case DateTimeInterface::class:
            case DateTime::class:
            case DateTimeImmutable::class:
                $question->setValidator(
                    static function (string $value) {
                        try {
                            new DateTimeImmutable($value);
                            return $value;
                        } catch (Exception) {
                            throw new InvalidArgumentException(
                                sprintf(
                                    'Failed to parse date "%s", please try again',
                                    $value
                                )
                            );
                        }
                    }
                );
                break;
            default:
                break;
        }
    }

    /**
     * Get a validator callback, for use in the console, to validate inputs against an enum.
     */
    private function getEnumValidatorCallback(string $enumClass): callable
    {
        return static function (string $value) use ($enumClass): string {
            if (!call_user_func([$enumClass, 'tryFrom'], $value)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Invalid enum value "%s", please try again',
                        $value
                    )
                );
            }

            return $value;
        };
    }

    /**
     * Get the values for an enum class to be used for autocompletion in the console.
     */
    private function getEnumAutocompleteValues(string $enumClass): array
    {
        return array_map(
            static fn(Provider $provider) => $provider->value,
            call_user_func([$enumClass, 'cases'])
        );
    }
}
