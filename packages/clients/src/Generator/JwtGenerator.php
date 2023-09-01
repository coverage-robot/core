<?php

namespace Packages\Clients\Generator;

use DateTimeImmutable;
use Exception;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Key\FileCouldNotBeRead;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\UnencryptedToken;
use Packages\Clients\Exception\ClientException;

class JwtGenerator
{
    public function __construct(private readonly string $privateKey)
    {
    }

    /**
     * @throws Exception
     */
    public function generate(string $issuer): UnencryptedToken
    {
        try {
            $config = Configuration::forSymmetricSigner(
                new Sha256(),
                $this->getPrivateKey()
            );
        } catch (FileCouldNotBeRead $e) {
            throw ClientException::authenticationException($e);
        }

        $now = new DateTimeImmutable('@' . time());

        return $config->builder()
            ->issuedBy($issuer)
            ->issuedAt($now)
            ->expiresAt($now->modify('+5 minutes'))
            ->getToken($config->signer(), $config->signingKey());
    }

    public function getPrivateKey(): Key
    {
        return InMemory::file($this->privateKey);
    }
}
