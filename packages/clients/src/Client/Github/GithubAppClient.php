<?php

namespace Packages\Clients\Client\Github;

use Exception;
use Github\Api\Apps;
use Github\AuthMethod;
use Github\Client;
use Github\HttpClient\Builder;
use Packages\Clients\Exception\ClientException;
use Packages\Clients\Generator\JwtGenerator;

class GithubAppClient extends Client
{
    /**
     * @param string $appId
     * @param JwtGenerator $jwtGenerator
     * @param Builder|null $httpClientBuilder
     * @param string|null $apiVersion
     * @param string|null $enterpriseUrl
     * @throws ClientException
     */
    public function __construct(
        private readonly string $appId,
        private readonly string $privateKeyFile,
        private readonly JwtGenerator $jwtGenerator,
        ?Builder $httpClientBuilder = null,
        ?string $apiVersion = null,
        public readonly ?string $enterpriseUrl = null
    ) {
        parent::__construct($httpClientBuilder, $apiVersion, $this->enterpriseUrl);

        $this->authenticateAsApp();
    }

    public function apps(): Apps
    {
        return parent::apps();
    }

    /**
     * @throws ClientException
     */
    private function authenticateAsApp(): void
    {
        try {
            $this->authenticate(
                $this->jwtGenerator->generate($this->appId, $this->privateKeyFile)
                    ->toString(),
                null,
                AuthMethod::JWT
            );
        } catch (Exception $e) {
            throw ClientException::authenticationException($e);
        }
    }
}
