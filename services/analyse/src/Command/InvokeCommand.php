<?php

namespace App\Command;

use App\Handler\EventHandler;
use Bref\Context\Context;
use Bref\Event\EventBridge\EventBridgeEvent;
use Bref\Event\InvalidLambdaEvent;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Model\Upload;
use Packages\Models\Model\Tag;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
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
#[AsCommand(name: 'app:invoke', description: 'Invoke the analysis handler')]
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
                'mock-parent-commit'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $upload = new Upload(
                'mock-uuid',
                Provider::GITHUB,
                $input->getArgument('owner'),
                $input->getArgument('repository'),
                $input->getArgument('commit'),
                [
                    $input->getArgument('parent')
                ],
                $input->getArgument('ref'),
                'mock-root',
                $input->getArgument('pullRequest'),
                new Tag($input->getArgument('tag'), $input->getArgument('commit')),
            );

            $this->handler->handleEventBridge(
                new EventBridgeEvent([
                    'detail-type' => Event::INGEST_SUCCESS,
                    'detail' => $this->serializer->serialize($upload, 'json')
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
