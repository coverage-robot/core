<?php

declare(strict_types=1);

namespace App\Command;

use App\Client\CognitoClient;
use App\Exception\AuthenticationException;
use App\Model\Tokens;
use App\Service\AuthTokenServiceInterface;
use App\Service\UniqueIdGeneratorService;
use Override;
use Packages\Contracts\Provider\Provider;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:new_project', description: 'Create a new project with tokens')]
final class NewProjectCommand
{
    public function __construct(
        private readonly CognitoClient $cognitoClient,
        private readonly AuthTokenServiceInterface $authTokenService,
        private readonly UniqueIdGeneratorService $uniqueIdGeneratorService
    ) {
    }

    /**
     * @throws AuthenticationException
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument(name: 'email', description: 'The contact email for the project')]
        string $email,
        #[Argument(name: 'repository', description: 'The repository the token belongs to')]
        string $repository,
        #[Argument(name: 'owner', description: 'The owner of the repository the token belongs to')]
        string $owner,
        #[Argument(name: 'provider', description: 'The VCS provider the repository belongs to')]
        string $provider,
    ): int
    {
        $uploadToken = $this->authTokenService->createNewUploadToken();
        $graphToken = $this->authTokenService->createNewGraphToken();
        $created = $this->cognitoClient->createProject(
            Provider::from($provider),
            $owner,
            $repository,
            $this->uniqueIdGeneratorService->generate(),
            $email,
            new Tokens(
                $uploadToken,
                $graphToken
            )
        );

        if ($created) {
            $io->writeln(sprintf('New upload token: %s, New graph token: %s', $uploadToken, $graphToken));
            return Command::SUCCESS;
        }

        $io->writeln('Failed to create project');
        return Command::FAILURE;
    }
}
