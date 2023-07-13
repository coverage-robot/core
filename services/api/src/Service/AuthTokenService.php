<?php

namespace App\Service;

use App\Enum\TokenType;
use App\Exception\TokenException;
use App\Model\GraphParameters;
use App\Model\ParametersInterface;
use App\Model\SigningParameters;
use App\Repository\ProjectRepository;
use Random\Randomizer;
use Symfony\Component\HttpFoundation\Request;

class AuthTokenService
{
    /**
     * Produces a token with a length of 50 (`TOKEN_LENGTH * 2`)
     */
    public const TOKEN_LENGTH = 25;
    public const MAX_TOKEN_RETRIES = 3;

    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly Randomizer $randomizer
    ) {
    }

    /**
     * Attempt to retrieve the upload token from a request.
     *
     * In practice this performs a lookup in the request headers for the
     * 'Authorization' key, and decodes it based on the Basic schema pattern.
     */
    public function getUploadTokenFromRequest(Request $request): ?string
    {
        if (!$request->headers->has('Authorization')) {
            return null;
        }

        $authHeader = $request->headers->get('Authorization');

        if (!is_string($authHeader) || !str_starts_with($authHeader, 'Basic ')) {
            return null;
        }


        // Decode the encoded token from the request header.
        $token = base64_decode(
            substr(
                (string)$authHeader,
                6
            )
        );

        // Remove the trailing colon from the token - which will have been added for the username:password pattern
        return trim($token, ':');
    }

    public function getGraphTokenFromRequest(Request $request): ?string
    {
        $graphToken = $request->query->get('token');

        if (!is_string($graphToken)) {
            return null;
        }

        return $graphToken;
    }

    /**
     * Validate a potential upload using a user-provided upload token.
     */
    public function validateParametersWithUploadToken(SigningParameters $parameters, string $token): bool
    {
        return $this->validateParametersWithToken(TokenType::UPLOAD, $parameters, $token);
    }

    public function validateParametersWithGraphToken(GraphParameters $parameters, string $token): bool
    {
        return $this->validateParametersWithToken(TokenType::GRAPH, $parameters, $token);
    }

    /**
     * Create a new unique upload token.
     */
    public function createNewUploadToken(): string
    {
        return $this->createNewToken(TokenType::UPLOAD);
    }

    /**
     * Create a new unique graph token.
     */
    public function createNewGraphToken(): string
    {
        return $this->createNewToken(TokenType::GRAPH);
    }

    private function validateParametersWithToken(
        TokenType $tokenType,
        ParametersInterface $parameters,
        string $token
    ): bool {
        $field = match ($tokenType) {
            TokenType::UPLOAD => 'uploadToken',
            TokenType::GRAPH => 'graphToken',
        };

        $project = $this->projectRepository
            ->findOneBy([
                $field => $token,
                'repository' => $parameters->getRepository(),
                'owner' => $parameters->getOwner(),
                'provider' => $parameters->getProvider(),
            ]);

        return $project !== null && $project->isEnabled();
    }

    private function createNewToken(TokenType $tokenType): string
    {
        $field = match ($tokenType) {
            TokenType::UPLOAD => 'uploadToken',
            TokenType::GRAPH => 'graphToken',
        };

        $attempts = 0;

        do {
            $token = bin2hex($this->randomizer->getBytes(self::TOKEN_LENGTH));

            $isUnique = $this->projectRepository->findOneBy([$field => $token]) === null;

            $attempts++;
        } while ($attempts < self::MAX_TOKEN_RETRIES && !$isUnique);

        if (!$isUnique) {
            throw TokenException::failedToCreateToken($attempts);
        }

        return $token;
    }
}
