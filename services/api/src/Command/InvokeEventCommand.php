<?php

namespace App\Command;

use App\Handler\EventHandler;
use Bref\Context\Context;
use Bref\Event\EventBridge\EventBridgeEvent;
use Bref\Event\InvalidLambdaEvent;
use Packages\Models\Enum\EventBus\CoverageEvent;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:invoke_event', description: 'Create a new project with tokens')]
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

    /**
     * @throws InvalidLambdaEvent
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->eventHandler->handleEventBridge(
            new EventBridgeEvent([
                'detail-type' => CoverageEvent::from($input->getArgument('event'))->value,
                'detail' => $input->getArgument('body'),
            ]),
            Context::fake()
        );

        return Command::SUCCESS;
    }
}
