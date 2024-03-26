<?php

namespace App\Client;

use App\Enum\EnvironmentVariable;
use App\Enum\TokenType;
use App\Exception\AuthenticationException;
use App\Model\Tokens;
use AsyncAws\CognitoIdentityProvider\CognitoIdentityProviderClient;
use AsyncAws\CognitoIdentityProvider\Enum\AuthFlowType;
use AsyncAws\CognitoIdentityProvider\Exception\UserNotFoundException;
use AsyncAws\CognitoIdentityProvider\Input\AdminConfirmSignUpRequest;
use AsyncAws\CognitoIdentityProvider\Input\AdminGetUserRequest;
use AsyncAws\CognitoIdentityProvider\Input\AdminInitiateAuthRequest;
use AsyncAws\CognitoIdentityProvider\Input\AdminSetUserPasswordRequest;
use AsyncAws\CognitoIdentityProvider\Input\AdminUpdateUserAttributesRequest;
use AsyncAws\CognitoIdentityProvider\Input\SignUpRequest;
use AsyncAws\CognitoIdentityProvider\Result\AdminGetUserResponse;
use AsyncAws\CognitoIdentityProvider\ValueObject\AttributeType;
use AsyncAws\Core\Exception\Http\HttpException;
use Override;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Contracts\Provider\Provider;
use Psr\Log\LoggerInterface;

final class CognitoClient implements CognitoClientInterface
{
    private const string PROVIDER_ATTRIBUTE = 'custom:provider';

    private const string OWNER_ATTRIBUTE = 'custom:owner';

    private const string REPOSITORY_ATTRIBUTE = 'custom:repository';

    private const string GRAPH_TOKEN_ATTRIBUTE = 'custom:graph_token';

    public function __construct(
        private readonly CognitoIdentityProviderClient $cognitoClient,
        private readonly EnvironmentServiceInterface $environmentService,
        private readonly LoggerInterface $cognitoClientLogger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function createProject(
        Provider $provider,
        string $owner,
        string $repository,
        string $email,
        Tokens $tokens
    ): bool {
        try {
            $username = $this->getUsername($provider, $owner, $repository);
            $userPoolId = $this->environmentService->getVariable(EnvironmentVariable::PROJECT_POOL_ID);
            $clientId = $this->environmentService->getVariable(EnvironmentVariable::PROJECT_POOL_CLIENT_ID);

            $response = $this->cognitoClient->signUp(
                new SignUpRequest(
                    [
                        'ClientId' => $clientId,
                        'SecretHash' => $this->getSecretHash($provider, $owner, $repository),
                        'Username' => $username,
                        'Password' => $tokens->getUploadToken(),
                        'UserAttributes' => [
                            new AttributeType([
                                'Name' => 'email',
                                'Value' => $email
                            ]),
                            new AttributeType([
                                'Name' => self::PROVIDER_ATTRIBUTE,
                                'Value' => $provider->value
                            ]),
                            new AttributeType([
                                'Name' => self::OWNER_ATTRIBUTE,
                                'Value' => $owner
                            ]),
                            new AttributeType([
                                'Name' => self::REPOSITORY_ATTRIBUTE,
                                'Value' => $repository
                            ]),
                            new AttributeType([
                                'Name' => self::GRAPH_TOKEN_ATTRIBUTE,
                                'Value' => $tokens->getGraphToken()
                            ])
                        ],
                    ]
                )
            );

            $response->resolve();

            // Confirm the sign up to lock in the Projects password (upload token) and attributes
            $response = $this->cognitoClient->adminConfirmSignUp(
                new AdminConfirmSignUpRequest(
                    [
                        'UserPoolId' => $userPoolId,
                        'Username' => $username,
                    ]
                )
            );

            $response->resolve();
        } catch (HttpException $httpException) {
            $this->cognitoClientLogger->error(
                'Failed to successfully create project.',
                [
                    'owner' => $owner,
                    'repository' => $repository,
                    'provider' => $provider->value,
                    'exception' => $httpException
                ]
            );

            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function doesProjectExist(Provider $provider, string $owner, string $repository): bool
    {
        try {
            return $this->getUser($provider, $owner, $repository) !== null;
        } catch (HttpException) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function authenticate(
        Provider $provider,
        string $owner,
        string $repository,
        TokenType $tokenType,
        string $token
    ): bool {
        return match ($tokenType) {
            TokenType::UPLOAD => $this->validateUploadToken(
                $provider,
                $owner,
                $repository,
                $token
            ),
            TokenType::GRAPH => $this->validateGraphToken(
                $provider,
                $owner,
                $repository,
                $token
            ),
        };
    }

    /**
     * @inheritDoc
     */
    public function setUploadToken(
        Provider $provider,
        string $owner,
        string $repository,
        string $uploadToken
    ): bool {
        $userPoolId = $this->environmentService->getVariable(EnvironmentVariable::PROJECT_POOL_ID);

        try {
            $response = $this->cognitoClient->adminSetUserPassword(
                new AdminSetUserPasswordRequest(
                    [
                        'UserPoolId' => $userPoolId,
                        'Username' => $this->getUsername(
                            $provider,
                            $owner,
                            $repository
                        ),
                        'Password' => $uploadToken,
                        'Permanent' => true
                    ]
                )
            );

            $response->resolve();
        } catch (HttpException $httpException) {
            $this->cognitoClientLogger->error(
                'Failed to set the upload token for project.',
                [
                    'owner' => $owner,
                    'repository' => $repository,
                    'provider' => $provider->value,
                    'exception' => $httpException
                ]
            );

            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function setGraphToken(
        Provider $provider,
        string $owner,
        string $repository,
        string $graphToken
    ): bool {
        $userPoolId = $this->environmentService->getVariable(EnvironmentVariable::PROJECT_POOL_ID);

        try {
            $response = $this->cognitoClient->adminUpdateUserAttributes(
                new AdminUpdateUserAttributesRequest(
                    [
                        'UserPoolId' => $userPoolId,
                        'Username' => $this->getUsername(
                            $provider,
                            $owner,
                            $repository
                        ),
                        'UserAttributes' => [
                            new AttributeType([
                                'Name' => self::GRAPH_TOKEN_ATTRIBUTE,
                                'Value' => $graphToken
                            ])
                        ]
                    ]
                )
            );

            $response->resolve();
        } catch (HttpException $httpException) {
            $this->cognitoClientLogger->error(
                'Failed to set the graph token for project.',
                [
                    'owner' => $owner,
                    'repository' => $repository,
                    'provider' => $provider->value,
                    'exception' => $httpException
                ]
            );

            return false;
        }

        return true;
    }

    /**
     * Check the upload token against the Cognito user's password, if it validates, then the token is correct.
     */
    private function validateUploadToken(
        Provider $provider,
        string $owner,
        string $repository,
        string $uploadToken
    ): bool {
        $userPoolId = $this->environmentService->getVariable(EnvironmentVariable::PROJECT_POOL_ID);
        $clientId = $this->environmentService->getVariable(EnvironmentVariable::PROJECT_POOL_CLIENT_ID);

        try {
            $response = $this->cognitoClient->adminInitiateAuth(
                new AdminInitiateAuthRequest(
                    [
                        'AuthFlow' => AuthFlowType::ADMIN_USER_PASSWORD_AUTH,
                        'ClientId' => $clientId,
                        'UserPoolId' => $userPoolId,
                        'AuthParameters' => [
                            'USERNAME' => $this->getUsername(
                                $provider,
                                $owner,
                                $repository
                            ),
                            'PASSWORD' => $uploadToken,
                            'SECRET_HASH' => $this->getSecretHash(
                                $provider,
                                $owner,
                                $repository
                            ),
                        ]
                    ]
                )
            );

            $response->resolve();

            return $response->getAuthenticationResult()?->getAccessToken() !== null;
        } catch (HttpException $httpException) {
            $this->cognitoClientLogger->error(
                "Failed to authenticate project's upload token.",
                [
                    'owner' => $owner,
                    'repository' => $repository,
                    'provider' => $provider->value,
                    'exception' => $httpException
                ]
            );

            return false;
        }
    }

    /**
     * Get a project's graph token from Cognito and compare it to the given token.
     */
    private function validateGraphToken(
        Provider $provider,
        string $owner,
        string $repository,
        string $token
    ): bool {
        try {
            $graphToken = $this->getGraphToken(
                $provider,
                $owner,
                $repository
            );
        } catch (HttpException | AuthenticationException $e) {
            $this->cognitoClientLogger->error(
                "Failed to authenticate project's graph token.",
                [
                    'owner' => $owner,
                    'repository' => $repository,
                    'provider' => $provider->value,
                    'exception' => $e
                ]
            );

            return false;
        }

        return $graphToken === $token;
    }

    /**
     * Get the attributes for a project from Cognito.
     *
     * @throws HttpException
     * @throws AuthenticationException
     */
    private function getGraphToken(
        Provider $provider,
        string $owner,
        string $repository
    ): string {
        $project = $this->getUser(
            $provider,
            $owner,
            $repository
        );

        foreach ($project->getUserAttributes() as $attributeType) {
            if ($attributeType->getName() !== self::GRAPH_TOKEN_ATTRIBUTE) {
                continue;
            }

            $graphToken = $attributeType->getValue();

            if ($graphToken === null) {
                throw AuthenticationException::invalidGraphToken();
            }

            return $graphToken;
        }

        throw AuthenticationException::invalidGraphToken();
    }

    /**
     * Get the project from Cognito.
     *
     * @throws HttpException
     */
    private function getUser(
        Provider $provider,
        string $owner,
        string $repository
    ): ?AdminGetUserResponse {
        $userPoolId = $this->environmentService->getVariable(EnvironmentVariable::PROJECT_POOL_ID);

        try {
            return $this->cognitoClient->adminGetUser(
                new AdminGetUserRequest(
                    [
                        'Username' => $this->getUsername(
                            $provider,
                            $owner,
                            $repository
                        ),
                        'UserPoolId' => $userPoolId
                    ]
                )
            );
        } catch (UserNotFoundException) {
            return null;
        }
    }

    /**
     * Get the full username for a user in Cognito.
     */
    private function getUsername(
        Provider $provider,
        string $owner,
        string $repository
    ): string {
        return sprintf(
            '%s-%s-%s',
            $owner,
            $provider->value,
            $repository
        );
    }

    /**
     * Compute the secret hash for the Cognito API when making admin requests.
     */
    private function getSecretHash(
        Provider $provider,
        string $owner,
        string $repository
    ): string {
        $clientId = $this->environmentService->getVariable(EnvironmentVariable::PROJECT_POOL_CLIENT_ID);
        $clientSecret = $this->environmentService->getVariable(EnvironmentVariable::PROJECT_POOL_CLIENT_SECRET);

        $hash = hash_hmac(
            'sha256',
            $this->getUsername($provider, $owner, $repository) . $clientId,
            $clientSecret,
            true
        );

        return base64_encode($hash);
    }
}
