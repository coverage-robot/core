<?php

namespace App\Command;

use App\Handler\EventHandler;
use Bref\Context\Context;
use Bref\Event\InvalidLambdaEvent;
use Bref\Event\S3\S3Event;
use DateTimeImmutable;
use DateTimeInterface;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This command is a helper for manually invoking the ingest handler locally, using the Localstack environment.
 *
 * To simulate production, the handler must be invoked from an S3 event, using a file available from S3 Localstack.
 *
 * @see README.md
 */
#[AsCommand(name: 'app:invoke', description: 'Invoke the ingest handler')]
class InvokeCommand extends Command
{
    private const BUCKET = 'coverage-ingest-%s';

    public function __construct(
        private readonly EventHandler $handler,
        private readonly EnvironmentServiceInterface $environmentService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('key', InputArgument::REQUIRED, 'The key of the file to retrieve');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->handler->handleS3(
                new S3Event([
                    'Records' => [
                        [
                            'eventSource' => 'aws:s3',
                            'eventTime' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
                            's3' => [
                                'bucket' => [
                                    'name' => sprintf(self::BUCKET, $this->environmentService->getEnvironment()->value),
                                    'arn' => 'mock-arn'
                                ],
                                'object' => [
                                    'key' => $input->getArgument('key')
                                ]
                            ]
                        ]
                    ]
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
