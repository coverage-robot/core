<?php

declare(strict_types=1);

namespace App\Command;

use App\Handler\EventHandler;
use Bref\Context\Context;
use Bref\Event\InvalidLambdaEvent;
use Bref\Event\Sqs\SqsEvent;
use Bref\Event\Sqs\SqsHandler;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Override;
use Packages\Contracts\Provider\Provider;
use Packages\Contracts\Tag\Tag;
use Packages\Event\Model\Upload;
use Packages\Message\PublishableMessage\PublishableCheckRunMessage;
use Packages\Message\PublishableMessage\PublishableCheckRunStatus;
use Packages\Message\PublishableMessage\PublishableLineCommentMessageCollection;
use Packages\Message\PublishableMessage\PublishableMessageCollection;
use Packages\Message\PublishableMessage\PublishableMissingCoverageLineCommentMessage;
use Packages\Message\PublishableMessage\PublishablePullRequestMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
final class InvokeCommand extends Command
{
    public function __construct(
        #[Autowire(service: EventHandler::class)]
        private readonly SqsHandler $handler,
        private readonly SerializerInterface $serializer
    ) {
        parent::__construct();
    }

    #[Override]
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

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $validUntil = DateTimeImmutable::createFromFormat(
                DateTimeInterface::ATOM,
                '2023-08-30T12:00:00+00:00'
            );

            if ($validUntil === false) {
                throw new InvalidArgumentException('Invalid date format for message validity');
            }

            $upload = new Upload(
                uploadId: 'mock-uuid',
                provider: Provider::GITHUB,
                projectId: 'mock-uuid',
                owner: 'mock-owner',
                repository: 'mock-repository',
                commit: 'mock-commit',
                parent: ['mock-parent'],
                ref: 'mock-ref',
                projectRoot: 'mock-project-root',
                tag: new Tag('mock-tag', 'mock-commit', [6]),
            );

            $sqsEvent = new SqsEvent(
                [
                    'Records' => [
                        [
                            'eventSource' => 'aws:sqs',
                            'messageId' => '1',
                            'body' => $this->serializer->serialize(
                                new PublishableMessageCollection(
                                    event: $upload,
                                    messages: [
                                        new PublishablePullRequestMessage(
                                            event: $upload,
                                            coveragePercentage: 100,
                                            diffCoveragePercentage: 0,
                                            diffUncoveredLines: 1,
                                            successfulUploads: 1,
                                            tagCoverage: [
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
                                            leastCoveredDiffFiles: [],
                                            uncoveredLinesChange: 1,
                                            coverageChange: 100,
                                            validUntil: $validUntil
                                        ),
                                        new PublishableCheckRunMessage(
                                            event: $upload,
                                            status: PublishableCheckRunStatus::SUCCESS,
                                            coveragePercentage: 100,
                                            baseCommit: null,
                                            coverageChange: 0,
                                            validUntil: $validUntil
                                        ),
                                        new PublishableLineCommentMessageCollection(
                                            event: $upload,
                                            messages: [
                                                new PublishableMissingCoverageLineCommentMessage(
                                                    event: $upload,
                                                    fileName: '.github/workflows/upload.yml',
                                                    startingOnMethod: true,
                                                    startLineNumber: 1,
                                                    endLineNumber: 100,
                                                    validUntil: $validUntil
                                                )
                                            ]
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
