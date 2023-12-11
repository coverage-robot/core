<?php

namespace App\Command;

use App\Handler\EventHandler;
use Bref\Context\Context;
use Bref\Event\InvalidLambdaEvent;
use Bref\Event\Sqs\SqsEvent;
use DateTimeInterface;
use Monolog\DateTimeImmutable;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Model\Upload;
use Packages\Message\PublishableMessage\PublishableCheckAnnotationMessage;
use Packages\Message\PublishableMessage\PublishableCheckRunMessage;
use Packages\Message\PublishableMessage\PublishableCheckRunStatus;
use Packages\Message\PublishableMessage\PublishableMessageCollection;
use Packages\Message\PublishableMessage\PublishableMissingCoverageAnnotationMessage;
use Packages\Message\PublishableMessage\PublishablePullRequestMessage;
use Packages\Models\Model\Tag;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * This command is a helper for manually invoking the publish handler locally.
 *
 * To simulate production, the handler must be invoked from a Sqs event, using parameters configured as arguments.
 *
 * Invoke the handler in a Docker container, closely simulating the Lambda environment:
 *
 * @see README.md
 */
#[AsCommand(name: 'app:invoke', description: 'Invoke the publish handler')]
class InvokeCommand extends Command
{
    public function __construct(
        private readonly EventHandler $handler,
        private readonly SerializerInterface $serializer
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('commit', InputArgument::REQUIRED, 'The commit to publish messages to')
            ->addArgument('pullRequest', InputArgument::REQUIRED, 'The pull request the commit belongs to')
            ->addArgument('repository', InputArgument::REQUIRED, 'The repository the commit belongs to')
            ->addArgument('owner', InputArgument::REQUIRED, 'The owner of the repository')
            ->addArgument(
                'tag',
                InputArgument::OPTIONAL,
                'The tag of the coverage file which is being published for',
                'mock-tag'
            )
            ->addArgument('ref', InputArgument::OPTIONAL, 'The ref of the commit being published to', 'mock-ref')
            ->addArgument(
                'parent',
                InputArgument::OPTIONAL,
                'The parent of the commit being published to',
                '["mock-parent-commit"]'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            /**
             * @var DateTimeImmutable $validUntil
             */
            $validUntil = DateTimeImmutable::createFromFormat(
                DateTimeInterface::ATOM,
                '2023-08-30T12:00:00+00:00'
            );

            $upload = new Upload(
                'mock-uuid',
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                'mock-commit',
                ['mock-parent'],
                'mock-ref',
                'mock-project-root',
                null,
                new Tag('mock-tag', 'mock-commit'),
            );

            $sqsEvent = new SqsEvent(
                [
                    'Records' => [
                        [
                            'eventSource' => 'aws:sqs',
                            'messageId' => '1',
                            'body' => $this->serializer->serialize(
                                new PublishableMessageCollection(
                                    $upload,
                                    [
                                        new PublishablePullRequestMessage(
                                            $upload,
                                            100,
                                            100,
                                            1,
                                            [
                                                [
                                                    'tag' => [
                                                        'name' => 'mock-tag',
                                                        'commit' => 'mock-commit',
                                                    ],
                                                    'lines' => 1,
                                                    'covered' => 1,
                                                    'partial' => 0,
                                                    'uncovered' => 0,
                                                    'coveragePercentage' => 100,
                                                ],
                                            ],
                                            [],
                                            $validUntil
                                        ),
                                        new PublishableCheckRunMessage(
                                            $upload,
                                            PublishableCheckRunStatus::SUCCESS,
                                            [
                                                new PublishableMissingCoverageAnnotationMessage(
                                                    $upload,
                                                    '.github/workflows/upload.yml',
                                                    true,
                                                    1,
                                                    100,
                                                    $validUntil
                                                )
                                            ],
                                            100,
                                            $validUntil
                                        )
                                    ]
                                ),
                                'json'
                            ),
                            'attributes' => [
                                'ApproximateReceiveCount' => '1',
                                'SentTimestamp' => '1234',
                                'SequenceNumber' => '1',
                                'MessageGroupId' => '1',
                                'SenderId' => '987',
                                'MessageDeduplicationId' => '1',
                                'ApproximateFirstReceiveTimestamp' => '1234'
                            ]
                        ]
                    ]
                ]
            );

            $this->handler->handleSqs(
                $sqsEvent,
                Context::fake()
            );

            return Command::SUCCESS;
        } catch (InvalidLambdaEvent $invalidLambdaEvent) {
            $output->writeln($invalidLambdaEvent->getMessage());
            return Command::FAILURE;
        }
    }
}
