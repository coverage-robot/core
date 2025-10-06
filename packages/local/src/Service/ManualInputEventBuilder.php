<?php

declare(strict_types=1);

namespace Packages\Local\Service;

use BackedEnum;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use InvalidArgumentException;
use LogicException;
use Override;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Model\EventInterface;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorResolverInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\TypeInfo\Type\ArrayShapeType;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\EnumType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\TypeIdentifier;

final readonly class ManualInputEventBuilder implements EventBuilderInterface
{
    public function __construct(
        private PropertyInfoExtractorInterface $propertyInfoExtractor,
        private ClassDiscriminatorResolverInterface $classDiscriminatorFromClassMetadata,
        private SerializerInterface&DenormalizerInterface $serializer,
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

    /**
     * @throws ExceptionInterface
     */
    #[Override]
    public function build(
        InputInterface $input,
        OutputInterface $output,
        HelperSet $helperSet,
        Event $event
    ): EventInterface {
        $payload = [];

        $discriminatorMap = $this->classDiscriminatorFromClassMetadata->getMappingForMappedObject(
            EventInterface::class
        );

        if (!$discriminatorMap) {
            throw new LogicException("Cannot manually build event if there is no discriminator map for the Event interface.");
        }

        $eventClass = $discriminatorMap->getClassForType($event->value);

        if (!$eventClass) {
            throw new LogicException(
                sprintf(
                    "Cannot build event manually for %s as it is not listed as a model on the Event interface.",
                    $event->value
                )
            );
        }

        /** @var list<string> $properties */
        $properties = $this->propertyInfoExtractor->getProperties($eventClass);

        /** @var QuestionHelper $helper */
        $helper = $helperSet->get('question');
        foreach ($properties as $index => $property) {
            if ($property === 'type') {
                // We can ignore the type property as that's for discrimination during denormalization
                continue;
            }

            if (!is_string($property)) {
                continue;
            }

            /** @var \Symfony\Component\TypeInfo\Type $types */
            $types = $this->propertyInfoExtractor->getType($eventClass, $property)?->traverse() ?? [];

            $question = new Question(
                sprintf(
                    '<question>(%s/%s) Enter value for "%s" (type: %s):</question>',
                    $index + 1,
                    count($properties),
                    $property,
                    (string)$types
                )
            );
            $this->setPropertyQuestionConstraintsBasedOnTypes($question, $types);

            /** @var mixed $answer */
            $answer = $helper->ask($input, $output, $question);

            $payload[$property] = $answer;
        }

        return $this->serializer->denormalize(
            $payload,
            $eventClass
        );
    }

    private function setPropertyQuestionConstraintsBasedOnTypes(Question $question, \Symfony\Component\TypeInfo\Type $types): void
    {
        if ($types instanceof BuiltinType && $types->getTypeIdentifier() === TypeIdentifier::ARRAY) {
            $question->setNormalizer(static fn(?string $value): ?array => $value !== null ? explode(',', $value) : $value);
            return;
        }

        if ($types instanceof EnumType && $types->getClassName() === Provider::class) {
            $question->setValidator($this->getEnumValidatorCallback(Provider::class));
            $question->setAutocompleterValues($this->getEnumAutocompleteValues(Provider::class));
            return;
        }

        if (
            $types instanceof ObjectType &&
            in_array(
                $types->getClassName(),
                [
                    DateTimeInterface::class,
                    DateTime::class,
                    DateTimeImmutable::class,
                ]
            )
        ) {
            $question->setValidator(
                static function (string $value): string {
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
        /** @var BackedEnum[] $cases */
        $cases = call_user_func([$enumClass, 'cases']);

        return array_map(
            static fn(BackedEnum $provider) => $provider->value,
            $cases
        );
    }
}
