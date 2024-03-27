<?php

namespace App\Command;

use App\Client\CognitoClient;
use App\Exception\AuthenticationException;
use App\Model\Tokens;
use App\Service\AuthTokenServiceInterface;
use Override;
use Packages\Contracts\Provider\Provider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:new_project', description: 'Create a new project with tokens')]
final class NewProjectCommand extends Command
{
    public function __construct(
        private readonly CognitoClient $cognitoClient,
        private readonly AuthTokenServiceInterface $authTokenService
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'The contact email for the project')
            ->addArgument('repository', InputArgument::REQUIRED, 'The repository the token belongs to')
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

        /** @var string $email */
        $email = $input->getArgument('email');

        $uploadToken = $this->authTokenService->createNewUploadToken();
        $graphToken = $this->authTokenService->createNewGraphToken();

        $created = $this->cognitoClient->createProject(
            Provider::from($provider),
            $owner,
            $repository,
            $email,
            new Tokens(
                $uploadToken,
                $graphToken
            )
        );

        if ($created) {
            $output->writeln(sprintf('New upload token: %s, New graph token: %s', $uploadToken, $graphToken));
            return Command::SUCCESS;
        }

        $output->writeln('Failed to create project');
        return Command::FAILURE;
    }
}
