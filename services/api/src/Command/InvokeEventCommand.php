<?php

namespace App\Command;

use App\Handler\EventHandler;
use Bref\Context\Context;
use Bref\Event\EventBridge\EventBridgeEvent;
use Bref\Event\InvalidLambdaEvent;
use Packages\Contracts\Event\Event;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:invoke_event', description: 'Invoke an event which the API listens for when testing locally')]
class InvokeEventCommand extends Command
{
    public function __construct(
        private readonly EventHandler $eventHandler
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('event', InputArgument::REQUIRED, 'The event to invoke')
            ->addArgument('body', InputArgument::REQUIRED, 'The body of the event (JSON)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->eventHandler->handleEventBridge(
                new EventBridgeEvent([
                    'detail-type' => Event::from($input->getArgument('event'))->value,
                    'detail' => $input->getArgument('body'),
                ]),
                Context::fake()
            );

            return Command::SUCCESS;
        } catch (InvalidLambdaEvent $e) {
            $output->writeln($e->getMessage());
            return Command::FAILURE;
        }
    }
}
