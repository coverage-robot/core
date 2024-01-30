<?php

namespace Packages\Local\Service;

use InvalidArgumentException;
use Override;
use Packages\Contracts\Event\Event;
use Packages\Event\Model\EventInterface;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class ExistingFixtureEventBuilder implements EventBuilderInterface
{
    public function __construct(
        private readonly SerializerInterface&DenormalizerInterface $serializer,
        private readonly string $fixtureDirectory = __DIR__ . '/../Fixture/'
    ) {
    }

    #[Override]
    public static function supports(InputInterface $input, Event $event): bool
    {
        return $input->getOption('fixture');
    }

    #[Override]
    public static function getPriority(): int
    {
        return 0;
    }

    #[Override]
    public function build(
        InputInterface $input,
        OutputInterface $output,
        ?HelperSet $helperSet,
        Event $event
    ): EventInterface {
        $availableFixtures = glob($this->fixtureDirectory . $event->value . '/*.json') ?? [];

        if ($availableFixtures === []) {
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

        $helper = $helperSet->get('question');

        $question = new ChoiceQuestion(
            'Choose a fixture to use as the event body',
            array_keys($availableFixtures)
        );

        $chosenFixture = $helper->ask($input, $output, $question);

        return $this->serializer->denormalize(
            array_merge(
                [
                    'type' => $event->value
                ],
                json_decode(
                    file_get_contents($availableFixtures[$chosenFixture]),
                    true
                )
            ),
            EventInterface::class
        );
    }
}
