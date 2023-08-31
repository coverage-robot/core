<?php

namespace Packages\Clients\Client\Github;

use DateTimeImmutable;
use Exception;
use Github\AuthMethod;
use Github\Client;
use Github\HttpClient\Builder;
use InvalidArgumentException;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\FileCouldNotBeRead;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;

class GithubAppClient extends Client
{
    /**
     * @throws Exception
     */
    public function __construct(
        private readonly string $appId,
        private readonly ?string $privateKey = null,
        ?Builder $httpClientBuilder = null,
        ?string $apiVersion = null,
        public readonly ?string $enterpriseUrl = null
    ) {
        parent::__construct($httpClientBuilder, $apiVersion, $this->enterpriseUrl);

        $this->authenticateAsApp();
    }

    private function authenticateAsApp(): void
    {
        if (!$this->privateKey) {
            return;
        }

        try {
            $config = Configuration::forSymmetricSigner(
                new Sha256(),
                InMemory::file($this->privateKey)
            );
        } catch (FileCouldNotBeRead $e) {
            throw new Exception('Unable to authenticate using the client.', 0, $e);
        }

        $now = new DateTimeImmutable('@' . time());
        $jwt = $config->builder()
            ->issuedBy($this->getAppId())
            ->issuedAt($now)
            ->expiresAt($now->modify('+5 minutes'))
            ->getToken($config->signer(), $config->signingKey());

        $this->authenticate($jwt->toString(), null, AuthMethod::JWT);
    }

    /**
     * @return non-empty-string
     */
    private function getAppId(): string
    {
        if (empty($this->appId)) {
            throw new InvalidArgumentException('App Id for Github app not provided.');
        }

        return $this->appId;
    }
}
