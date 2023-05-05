<?php

namespace App\Client\Github;

use DateTimeImmutable;
use Exception;
use Github\AuthMethod;
use Github\Client;
use Github\HttpClient\Builder;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;

class GithubAppClient extends Client
{
    private const APP_ID = '327333';

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

        $config = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::file(self::PRIVATE_KEY)
        );

        $now = new DateTimeImmutable('@' . time());
        $jwt = $config->builder()
            ->issuedBy(self::APP_ID)
            ->issuedAt($now)
            ->expiresAt($now->modify('+5 minutes'))
            ->getToken($config->signer(), $config->signingKey());

        $this->authenticate($jwt->toString(), null, AuthMethod::JWT);
    }
}
