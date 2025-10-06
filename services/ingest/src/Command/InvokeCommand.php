<?php

declare(strict_types=1);

namespace App\Command;

use App\Handler\EventHandler;
use Bref\Context\Context;
use Bref\Event\InvalidLambdaEvent;
use Bref\Event\S3\S3Event;
use Bref\Event\S3\S3Handler;
use DateTimeImmutable;
use DateTimeInterface;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * This command is a helper for manually invoking the ingest handler locally, using the Localstack environment.
 *
 * To simulate production, the handler must be invoked from an S3 event, using a file available from S3 Localstack.
 *
 * @see README.md
 */
#[AsCommand(name: 'app:invoke', description: 'Invoke the ingest handler')]
final readonly class InvokeCommand
{
    private const string BUCKET = 'coverage-ingest-%s';

    public function __construct(
        #[Autowire(service: EventHandler::class)]
        private S3Handler $handler,
        private EnvironmentServiceInterface $environmentService
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The key of the file to retrieve', name: 'key')]
        string $key,
    ): int {
        try {
            $this->handler->handleS3(
                new S3Event([
                    'Records' => [
                        [
                            'eventSource' => 'aws:s3',
                            'eventTime' => new DateTimeImmutable()->format(DateTimeInterface::ATOM),
                            's3' => [
                                'bucket' => [
                                    'name' => sprintf(self::BUCKET, $this->environmentService->getEnvironment()->value),
                                    'arn' => 'mock-arn'
                                ],
                                'object' => [
                                    'key' => $key
                                ]
                            ]
                        ]
                    ]
                ]),
                Context::fake()
            );

            return Command::SUCCESS;
        } catch (InvalidLambdaEvent $invalidLambdaEvent) {
            $io->writeln($invalidLambdaEvent->getMessage());
            return Command::FAILURE;
        }
    }
}
