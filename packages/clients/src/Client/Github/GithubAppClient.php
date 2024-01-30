<?php

namespace Packages\Clients\Client\Github;

use Exception;
use Github\Api\Apps;
use Github\AuthMethod;
use Github\Client;
use Github\HttpClient\Builder;
use Packages\Clients\Exception\ClientException;
use Packages\Clients\Generator\JwtGenerator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class GithubAppClient extends Client
{
    /**
     * @throws ClientException
     */
    public function __construct(
        #[Autowire(env: 'GITHUB_APP_ID')]
        private readonly string $appId,
        #[Autowire(value: '%kernel.project_dir%/config/github.pem')]
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
        } catch (Exception $exception) {
            throw ClientException::authenticationException($exception);
        }
    }
}
