<?php

namespace Packages\Clients\Generator;

use DateTimeImmutable;
use Exception;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\FileCouldNotBeRead;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\UnencryptedToken;
use Packages\Clients\Exception\ClientException;

class JwtGenerator
{
    /**
     * @throws Exception
     */
    public function generate(string $issuer, string $privateKeyFile): UnencryptedToken
    {
        try {
            $config = Configuration::forSymmetricSigner(
                new Sha256(),
                InMemory::file($privateKeyFile)
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
}
