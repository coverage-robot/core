<?php

namespace App\Client\Github;

use App\Enum\EnvironmentVariable;
use App\Exception\ClientException;
use App\Service\EnvironmentService;
use DateTimeImmutable;
use Exception;
use Github\AuthMethod;
use Github\Client;
use Github\HttpClient\Builder;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\FileCouldNotBeRead;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;

class GithubAppClient extends Client
{
    private const PRIVATE_KEY = __DIR__ . '/../../../config/github.pem';

    /**
     * @throws Exception
     */
    public function __construct(
        private readonly EnvironmentService $environmentService,
        ?Builder $httpClientBuilder = null,
        ?string $apiVersion = null,
        public readonly ?string $enterpriseUrl = null
    ) {
        parent::__construct($httpClientBuilder, $apiVersion, $this->enterpriseUrl);

        $this->authenticateAsApp();
    }

    private function authenticateAsApp(): void
    {
        try {
            $config = Configuration::forSymmetricSigner(
                new Sha256(),
                InMemory::file(self::PRIVATE_KEY)
            );
        } catch (FileCouldNotBeRead $e) {
            // Attempt to read the key file. If it fails, we can't authenticate. This
            // is usually the result of running tests, but can happen if configured
            // incorrectly.
            throw ClientException::authenticationException($e);
        }

        $now = new DateTimeImmutable('@' . time());
        $jwt = $config->builder()
            ->issuedBy($this->environmentService->getVariable(EnvironmentVariable::GITHUB_APP_ID))
            ->issuedAt($now)
            ->expiresAt($now->modify('+5 minutes'))
            ->getToken($config->signer(), $config->signingKey());

        $this->authenticate($jwt->toString(), null, AuthMethod::JWT);
    }
}
