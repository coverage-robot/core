<?php

declare(strict_types=1);

namespace Packages\Local\Service;

use InvalidArgumentException;
use Override;
use Packages\Contracts\Event\Event;
use Packages\Event\Model\EventInterface;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

final readonly class ExistingFixtureEventBuilder implements EventBuilderInterface
{
    public function __construct(
        private SerializerInterface&DenormalizerInterface $serializer,
        private string $fixtureDirectory = __DIR__ . '/../Fixture/'
    ) {
    }

    #[Override]
    public static function supports(InputInterface $input, Event $event): bool
    {
        return (bool) $input->getOption('fixture');
    }

    #[Override]
    public static function getPriority(): int
    {
        return 0;
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
        $availableFixtures = glob($this->fixtureDirectory . $event->value . '/*.json');

        if ($availableFixtures === [] || $availableFixtures === false) {
            throw new InvalidArgumentException(
                sprintf(
                    'No fixtures available for %s',
                    $event->value
                )
            );
        }

        $availableFixtures = array_reduce(
            $availableFixtures,
            static function (array $carry, string $filePath): array {
                $carry[basename($filePath)] = $filePath;
                return $carry;
            },
            []
        );

        /** @var QuestionHelper $helper */
        $helper = $helperSet->get('question');

        $question = new ChoiceQuestion(
            'Choose a fixture to use as the event body',
            array_keys($availableFixtures)
        );

        /** @var string $chosenFixture */
        $chosenFixture = $helper->ask($input, $output, $question);

        /** @var array $file */
        $file = json_decode(
            (string)file_get_contents((string)$availableFixtures[$chosenFixture]),
            true,
            JSON_THROW_ON_ERROR
        );

        /** @var EventInterface $eventModel */
        $eventModel = $this->serializer->denormalize(
            array_merge(
                [
                    'type' => $event->value
                ],
                $file
            ),
            EventInterface::class
        );

        return $eventModel;
    }
}
