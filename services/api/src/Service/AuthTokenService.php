<?php

namespace App\Service;

use App\Client\CognitoClient;
use App\Client\CognitoClientInterface;
use App\Enum\TokenType;
use App\Exception\TokenException;
use App\Model\GraphParameters;
use App\Model\ParametersInterface;
use App\Model\SigningParameters;
use App\Repository\ProjectRepository;
use Psr\Log\LoggerInterface;
use Random\Randomizer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

final class AuthTokenService implements AuthTokenServiceInterface
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        #[Autowire(service: CognitoClient::class)]
        private readonly CognitoClientInterface $cognitoClient,
        private readonly Randomizer $randomizer,
        private readonly LoggerInterface $authTokenLogger
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
        $this->authTokenLogger->info(
            'Attempting to retrieve upload token from request.',
            [
                'headers' => $request->headers->all(),
                'request' => $request->toArray()
            ]
        );

        if (!$request->headers->has('Authorization')) {
            $this->authTokenLogger->info(
                'No authorization header provided, which means theres no valid upload token for the request.',
                [
                    'headers' => $request->headers->all(),
                    'request' => $request->toArray()
                ]
            );
            return null;
        }

        $authHeader = $request->headers->get('Authorization');

        if ($authHeader === null) {
            $this->authTokenLogger->info(
                'Authorization header not provided.',
                [
                    'request' => $request->toArray()
                ]
            );

            return null;
        }

        if (!str_starts_with($authHeader, 'Basic ')) {
            $this->authTokenLogger->info(
                'Authorization header provided, but in an unsupported wrong format (not Basic).',
                [
                    'headers' => $authHeader,
                    'request' => $request->toArray()
                ]
            );
            return null;
        }

        // Decode the encoded token from the request header
        $uploadToken = base64_decode(
            substr(
                $authHeader,
                6
            )
        );

        // Remove the trailing colon from the token - which will have been added for the username:password pattern
        $uploadToken = trim($uploadToken, ':');

        $this->authTokenLogger->info(
            'Upload token decoded successfully.',
            [
                'uploadToken' => $uploadToken,
                'request' => $request->toArray()
            ]
        );

        return $uploadToken;
    }

    public function getGraphTokenFromRequest(Request $request): ?string
    {
        $graphToken = $request->query->get('token');

        if (!is_string($graphToken)) {
            $this->authTokenLogger->info(
                'Graph token not provided in request.',
                [
                    'parameters' => $request->query->all()
                ]
            );

            return null;
        }


        $this->authTokenLogger->info(
            'Graph token decoded successfully.',
            [
                'token' => $graphToken,
                'parameters' => $request->query->all()
            ]
        );

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

        $isAuthenticatedByDatabase = $project !== null && $project->isEnabled();

        $isAuthenticatedByCognito = $this->cognitoClient->authenticate(
            $parameters->getProvider(),
            $parameters->getOwner(),
            $parameters->getRepository(),
            $tokenType,
            $token
        );

        if ($isAuthenticatedByCognito !== $isAuthenticatedByDatabase) {
            /**
             * Temporarily mirror the database check against Cognito to identify mismatches in
             * behaviour.
             */
            $this->authTokenLogger->error(
                'Token validation mismatch between database and Cognito.',
                [
                    'tokenType' => $tokenType,
                    'parameters' => $parameters,
                    'token' => $token,
                    'isAuthenticatedByDatabase' => $isAuthenticatedByDatabase,
                    'isAuthenticatedByCognito' => $isAuthenticatedByCognito,
                ]
            );
        }

        return $isAuthenticatedByDatabase;
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

            ++$attempts;
        } while ($attempts < self::MAX_TOKEN_RETRIES && !$isUnique);

        if (!$isUnique) {
            $this->authTokenLogger->critical(
                sprintf('Failed to create a unique token after %s attempts.', $attempts),
                [
                    'attempts' => $attempts,
                    'tokenType' => $tokenType,
                ]
            );

            throw TokenException::failedToCreateToken($attempts);
        }

        return $token;
    }
}
