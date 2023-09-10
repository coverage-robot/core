<?php

namespace App\Command;

use App\Handler\EventHandler;
use Bref\Context\Context;
use Bref\Event\EventBridge\EventBridgeEvent;
use Bref\Event\InvalidLambdaEvent;
use Packages\Models\Enum\EventBus\CoverageEvent;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Event\Upload;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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

            $this->handler->handleEventBridge(
                new EventBridgeEvent([
                    'detail-type' => CoverageEvent::INGEST_SUCCESS,
                    'detail' => $upload->jsonSerialize()
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
