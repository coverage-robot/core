<?php

namespace App\Service;

use App\Model\SigningParameters;
use App\Repository\ProjectRepository;
use Exception\AuthenticationException;
use Random\Randomizer;
use Symfony\Component\HttpFoundation\Request;

class AuthTokenService
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly Randomizer $randomizer
    ) {
    }

    /**
     * Attempt to retrieve the project token from a request.
     *
     * In practice this performs a lookup in the request headers for the
     * 'token' key.
     */
    public function getProjectTokenFromRequest(Request $request): ?string
    {
        return  $request->headers->get('token');
    }

    /**
     * Validate a potential upload using a user-provided project token.
     */
    public function validateParametersWithProjectToken(SigningParameters $parameters, string $token): bool
    {
        $project = $this->projectRepository
            ->findOneBy([
                'token' => $token,
                'repository' => $parameters->getRepository(),
                'owner' => $parameters->getOwner(),
                'provider' => $parameters->getProvider(),
            ]);

        return $project !== null && $project->isEnabled();
    }

    /**
     * Create a new unique project token.
     * @throws AuthenticationException
     */
    public function createNewProjectToken(): string
    {
        $attempts = 0;

        do {
            $projectToken = bin2hex($this->randomizer->getBytes(12));

            $isUnique = $this->projectRepository
                    ->findOneBy(['token' => $projectToken]) === null;

            $attempts++;
        } while ($attempts <= 3 && !$isUnique);

        if (!$isUnique) {
            throw AuthenticationException::failedToCreateProjectToken($attempts);
        }

        return $projectToken;
    }
}
