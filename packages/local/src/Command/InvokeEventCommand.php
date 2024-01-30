<?php

namespace Packages\Local\Command;

use Bref\Context\Context;
use Bref\Event\EventBridge\EventBridgeEvent;
use Bref\Event\EventBridge\EventBridgeHandler;
use Packages\Contracts\Event\Event;
use Packages\Event\Handler\EventHandler;
use Packages\Event\Model\EventInterface;
use Packages\Local\Service\EventBuilderInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * A command designed to be used inside services which can manually invoke
 * an event handler with a custom event.
 */
final class InvokeEventCommand extends Command
{
    /**
     * @param EventBuilderInterface[] $eventBuilders
     */
    public function __construct(
        private readonly SerializerInterface&NormalizerInterface $serializer,
        #[Autowire(service: EventHandler::class)]
        private readonly EventBridgeHandler $eventHandler,
        #[TaggedIterator('package.local.event_builder', defaultPriorityMethod: 'getPriority')]
        private readonly iterable $eventBuilders,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Invoke the services event handler with a custom event')
            ->addArgument(
                'event',
                InputOption::VALUE_REQUIRED,
                'The event to invoke the handler with',
                null,
                array_map(static fn(Event $event) => $event->value, Event::cases())
            )
            ->addOption(
                'file',
                null,
                InputOption::VALUE_REQUIRED,
                'A pre-existing JSON payload to use as the event body',
                null
            )
            ->addOption(
                'fixture',
                null,
                InputOption::VALUE_NONE,
                'Use pre-existing fixture to use as the event body',
                null
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $event = Event::tryFrom(strtoupper($input->getArgument('event')));

        if (!$event instanceof \Packages\Contracts\Event\Event) {
            $output->writeln(
                [
                    '<error>',
                    'Invalid event provided, must be one of:',
                    ...array_map(static fn(Event $event) => $event->value, Event::cases()),
                    '</error>'
                ]
            );

            return Command::FAILURE;
        }

        try {
            $builtEvent = $this->buildEvent(
                $input,
                $output,
                $event
            );

            if (!$builtEvent instanceof \Packages\Event\Model\EventInterface) {
                $output->writeln('<error>No builder available to build event.</error>');
                return Command::FAILURE;
            }

            $output->writeln(
                sprintf(
                    '<info>Invoking event handler for event: %s</info>',
                    $builtEvent->getType()
                        ->value
                )
            );

            $this->eventHandler->handleEventBridge(
                new EventBridgeEvent([
                    'detail-type' => $builtEvent->getType()
                        ->value,
                    'detail' => $this->serializer->normalize($builtEvent)
                ]),
                Context::fake()
            );
        } catch (ExceptionInterface $exception) {
            $output->writeln(
                [
                    '<error>',
                    'Failed to build event, see error below:',
                    $exception->getMessage(),
                    '</error>'
                ]
            );

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Select the highest priority event builder and use it
     * to build a valid event which should be invoked against
     * the event handler.
     */
    private function buildEvent(
        InputInterface $input,
        OutputInterface $output,
        Event $event
    ): ?EventInterface {
        foreach ($this->eventBuilders as $eventBuilder) {
            if (!$eventBuilder->supports($input, $event)) {
                continue;
            }

            return $eventBuilder->build(
                $input,
                $output,
                $this->getHelperSet(),
                $event
            );
        }

        return null;
    }
}
