<?php

namespace App\Client\Github;

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
    public const APP_ID = '327333';

    public const BOT_ID = 'BOT_kgDOB-Qpag';

    private const PRIVATE_KEY = __DIR__ . '/../../../config/github.pem';

    /**
     * @throws Exception
     */
    public function __construct(
        ?Builder $httpClientBuilder = null,
        ?string $apiVersion = null,
        public readonly ?string $enterpriseUrl = null
    ) {
        parent::__construct($httpClientBuilder, $apiVersion, $this->enterpriseUrl);

        $this->authenticateAsApp();
    }

    private function authenticateAsApp(): bool
    {
        try {
            $config = Configuration::forSymmetricSigner(
                new Sha256(),
                InMemory::file(self::PRIVATE_KEY)
            );
        } catch (FileCouldNotBeRead) {
            // Attempt to read the key file. If it fails, we can't authenticate. This
            // is usually the result of running tests, but can happen if configured
            // incorrectly.
            return false;
        }

        $now = new DateTimeImmutable('@' . time());
        $jwt = $config->builder()
            ->issuedBy(self::APP_ID)
            ->issuedAt($now)
            ->expiresAt($now->modify('+5 minutes'))
            ->getToken($config->signer(), $config->signingKey());

        $this->authenticate($jwt->toString(), null, AuthMethod::JWT);

        return true;
    }
}
