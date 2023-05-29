<?php

namespace App\Command;

use App\Enum\ProviderEnum;
use App\Handler\AnalyseHandler;
use App\Model\Upload;
use Bref\Context\Context;
use Bref\Event\InvalidLambdaEvent;
use Bref\Event\Sqs\SqsEvent;
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
 * `docker-compose run --rm analyse php bin/console app:invoke <commit> <pullRequest> <repository> <owner> <parent> -vv`
 */
#[AsCommand(name: 'app:invoke', description: 'Invoke the analysis handler')]
class InvokeCommand extends Command
{
    public function __construct(private readonly AnalyseHandler $handler)
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
                'parent',
                InputArgument::OPTIONAL,
                'The parent of the commit to analyse',
                'mock-parent-commit'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $upload = new Upload([
                'uploadId' => 'mock-uuid',
                'provider' => ProviderEnum::GITHUB->value,
                'commit' => $input->getArgument('commit'),
                'parent' => $input->getArgument('parent'),
                'owner' => $input->getArgument('owner'),
                'repository' => $input->getArgument('repository'),
                'pullRequest' => $input->getArgument('pullRequest')
            ]);

            $this->handler->handleSqs(
                new SqsEvent([
                    'Records' => [
                        [
                            'eventSource' => 'aws:sqs',
                            'messageId' => 'mock',
                            'body' => json_encode($upload),
                            'messageAttributes' => []
                        ]
                    ]
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
