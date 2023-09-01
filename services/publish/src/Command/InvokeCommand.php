<?php

namespace App\Command;

use App\Handler\EventHandler;
use Bref\Context\Context;
use Bref\Event\InvalidLambdaEvent;
use Bref\Event\Sqs\SqsEvent;
use DateTimeInterface;
use Monolog\DateTimeImmutable;
use Packages\Models\Enum\LineState;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\PublishableMessage\PublishableCheckAnnotationMessage;
use Packages\Models\Model\PublishableMessage\PublishableCheckRunMessage;
use Packages\Models\Model\PublishableMessage\PublishableMessageCollection;
use Packages\Models\Model\PublishableMessage\PublishablePullRequestMessage;
use Packages\Models\Model\Upload;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
    public function __construct(private readonly EventHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('commit', InputArgument::REQUIRED, 'The commit to analyse')
            ->addArgument('pullRequest', InputArgument::REQUIRED, 'The pull request the commit belongs to')
            ->addArgument('repository', InputArgument::REQUIRED, 'The repository the commit belongs to')
            ->addArgument('owner', InputArgument::REQUIRED, 'The owner of the repository')
            ->addArgument(
                'tag',
                InputArgument::OPTIONAL,
                'The tag of the coverage file which is being analysed',
                'mock-tag'
            )
            ->addArgument('ref', InputArgument::OPTIONAL, 'The ref of the commit to analyse', 'mock-ref')
            ->addArgument(
                'parent',
                InputArgument::OPTIONAL,
                'The parent of the commit to analyse',
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

            $upload = Upload::from([
                'uploadId' => 'mock-uuid',
                'provider' => Provider::GITHUB->value,
                'commit' => $input->getArgument('commit'),
                'parent' => $input->getArgument('parent'),
                'ref' => $input->getArgument('ref'),
                'owner' => $input->getArgument('owner'),
                'repository' => $input->getArgument('repository'),
                'tag' => $input->getArgument('tag'),
                'pullRequest' => $input->getArgument('pullRequest')
            ]);

            $sqsEvent = new SqsEvent(
                [
                    'Records' => [
                        [
                            'eventSource' => 'aws:sqs',
                            'messageId' => '1',
                            'body' => json_encode(
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
                                            [
                                                new PublishableCheckAnnotationMessage(
                                                    $upload,
                                                    '.github/workflows/upload.yml',
                                                    6,
                                                    LineState::UNCOVERED,
                                                    $validUntil
                                                )
                                            ],
                                            100,
                                            $validUntil
                                        )
                                    ]
                                )
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
        } catch (InvalidLambdaEvent $e) {
            $output->writeln($e->getMessage());
            return Command::FAILURE;
        }
    }
}
