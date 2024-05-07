<?php

namespace Packages\Clients\Client\Github;

use Exception;
use Github\Api\AbstractApi;
use Github\Api\Apps;
use Github\AuthMethod;
use Github\Client;
use Github\HttpClient\Builder;
use Http\Client\Common\Plugin\RetryPlugin;
use Override;
use Packages\Clients\Exception\ClientException;
use Packages\Clients\Generator\JwtGenerator;
use Packages\Telemetry\Service\MetricServiceInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class GithubAppClient extends Client
{
    private bool $isAuthenticated = false;

    /**
     * @throws ClientException
     */
    public function __construct(
        #[Autowire(env: 'GITHUB_APP_ID')]
        private readonly string $appId,
        #[Autowire(value: '%kernel.project_dir%/config/github.pem')]
        private readonly string $privateKeyFile,
        private readonly JwtGenerator $jwtGenerator,
        private readonly MetricServiceInterface $metricService,
        ?Builder $httpClientBuilder = null,
        ?string $apiVersion = null,
        public readonly ?string $enterpriseUrl = null
    ) {
        parent::__construct($httpClientBuilder, $apiVersion, $this->enterpriseUrl);

        /**
         * Retry requests up to 2 additional times when a failure occurs.
         *
         * GitHub fails occasionally, usually occurring at benign points in time, like when attempting to
         * retrieve commit history. In this case, it's safe to retry the request and see if we can get a
         * response 1 or 2 more times.
         */
        $this->getHttpClientBuilder()
            ->addPlugin(
                new RetryPlugin([
                    'retries' => 2
                ])
            );
    }

    /**
     * Before performing any API requests, the client must authenticate as an app.
     *
     * This lazily authenticates the client as the app, just before the first API request is made.
     *
     * @throws ClientException
     */
    #[Override]
    public function api($name): AbstractApi
    {
        if ( ! $this->isAuthenticated) {
            $this->authenticateAsApp();
        }

        return parent::api($name);
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
            $this->metricService->increment(metric: 'GithubAppAuthenticationRequests');

            $this->authenticate(
                $this->jwtGenerator->generate($this->appId, $this->privateKeyFile)
                    ->toString(),
                null,
                AuthMethod::JWT
            );

            $this->isAuthenticated = true;
        } catch (Exception $exception) {
            throw ClientException::authenticationException($exception);
        }
    }
}
