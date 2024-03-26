<?php

namespace App\Client;

use App\Enum\TokenType;
use App\Exception\AuthenticationException;
use App\Model\Tokens;
use Packages\Contracts\Provider\Provider;

interface CognitoClientInterface
{
    /**
     * Create a new project with authentication tokens in Cognito.
     */
    public function createProject(
        Provider $provider,
        string $owner,
        string $repository,
        string $email,
        Tokens $tokens
    ): bool;

    /**
     * Check if the project exists in Cognito.
     */
    public function doesProjectExist(
        Provider $provider,
        string $owner,
        string $repository
    ): bool;

    /**
     * Authenticate a request for a project, using a given token.
     *
     * This could be:
     * 1. An Upload token (secret, and used to securely upload coverage files)
     * 2. A Graph token (not secret, and used to build graphs and badges)
     *
     * @throws AuthenticationException
     */
    public function authenticate(
        Provider $provider,
        string $owner,
        string $repository,
        TokenType $tokenType,
        string $token
    ): bool;

    /**
     * Set the upload token for a project in Cognito.
     *
     * This _is_ a secret, and so is recorded as the project's password in Cognito.
     */
    public function setUploadToken(
        Provider $provider,
        string $owner,
        string $repository,
        string $uploadToken
    ): bool;

    /**
     * Set the graph token for a project in Cognito.
     *
     * This is recorded as a custom attribute on the project in Cognito as its not a secret which
     * cannot be exposed in plain text, and the password is already used for the upload token - which
     * is a secret.
     */
    public function setGraphToken(
        Provider $provider,
        string $owner,
        string $repository,
        string $graphToken
    ): bool;
}
