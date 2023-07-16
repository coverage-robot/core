<?php

namespace App\Command;

use App\Service\History\CommitHistoryService;
use App\Service\QueryService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:tree_builder', description: 'Built the tree of commits from a root')]
class CommitTreeBuilderCommand extends Command
{
    public function __construct(private readonly QueryService $queryService, private readonly CommitHistoryService $commitHistoryService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('commit', InputArgument::OPTIONAL, 'The commit to analyse');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {


        return Command::SUCCESS;
    }
}
