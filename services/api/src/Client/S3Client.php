<?php

namespace App\Client;

use AsyncAws\Core\Credentials\CredentialProvider;
use Packages\Telemetry\XRayHttpClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class S3Client extends \AsyncAws\S3\S3Client implements PresignableClientInterface
{
    public function __construct(
        $configuration = [],
        ?CredentialProvider $credentialProvider = null,
        #[Autowire(service: XRayHttpClient::class)]
        ?HttpClientInterface $httpClient = null,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($configuration, $credentialProvider, $httpClient, $logger);
    }
}
