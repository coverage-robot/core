<?php

namespace App\Command;

use App\Handler\EventHandler;
use Bref\Context\Context;
use Bref\Event\EventBridge\EventBridgeEvent;
use Bref\Event\InvalidLambdaEvent;
use DateTimeImmutable;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Enum\JobState;
use Packages\Event\Model\JobStateChange;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * This command is a helper for manually invoking the analysis handler locally.
 *
 * To simulate production, the handler must be invoked from a Sqs event, using parameters configured as arguments.
 *
 * Invoke the handler in a Docker container, closely simulating the Lambda environment:
 *
 * @see README.md
 */
#[AsCommand(name: 'app:invoke', description: 'Invoke the orchestration event handler')]
class InvokeCommand extends Command
{
    public function __construct(
        private readonly EventHandler $handler,
        private readonly SerializerInterface&NormalizerInterface $serializer
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $jobStateChange = new JobStateChange(
                Provider::GITHUB,
                'coverage-robot',
                'core',
                'mock-ref',
                '12db14417f44a5371fe1c95171d6f96e4e210138',
                [],
                null,
                null,
                null,
                'mock-job-id',
                JobState::COMPLETED,
                new DateTimeImmutable()
            );

            $this->handler->handleEventBridge(
                new EventBridgeEvent([
                    'detail-type' => Event::JOB_STATE_CHANGE->value,
                    'detail' => $this->serializer->normalize($jobStateChange, 'array')
                ]),
                Context::fake()
            );

            return Command::SUCCESS;
        } catch (InvalidLambdaEvent $invalidLambdaEvent) {
            $output->writeln($invalidLambdaEvent->getMessage());
            return Command::FAILURE;
        }
    }
}
