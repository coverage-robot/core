<?php

namespace App\Command;

use App\Entity\Project;
use App\Exception\AuthenticationException;
use App\Repository\ProjectRepository;
use App\Service\AuthTokenService;
use Override;
use Packages\Contracts\Provider\Provider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:new_project', description: 'Create a new project with tokens')]
class NewProjectCommand extends Command
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly AuthTokenService $authTokenService
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('repository', InputArgument::REQUIRED, 'The repository the token belongs to')
            ->addArgument('owner', InputArgument::REQUIRED, 'The owner of the repository the token belongs to')
            ->addArgument('provider', InputArgument::REQUIRED, 'The VCS provider the repository belongs to');
    }

    /**
     * @throws AuthenticationException
     */
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $provider */
        $provider = $input->getArgument('provider');

        /** @var string $repository */
        $repository = $input->getArgument('repository');

        /** @var string $owner */
        $owner = $input->getArgument('owner');

        $uploadToken = $this->authTokenService->createNewUploadToken();
        $graphToken = $this->authTokenService->createNewGraphToken();

        $project = (new Project())->setProvider(Provider::from($provider))
            ->setRepository($repository)
            ->setOwner($owner)
            ->setUploadToken($uploadToken)
            ->setGraphToken($graphToken);

        $this->projectRepository->save($project, true);

        $output->writeln(sprintf('New upload token: %s, New graph token: %s', $uploadToken, $graphToken));

        return Command::SUCCESS;
    }
}
