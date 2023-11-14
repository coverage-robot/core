<?php

namespace Packages\Telemetry;

use AsyncAws\Core\AwsError\ChainAwsErrorFactory;
use AsyncAws\Core\HttpClient\AwsRetryStrategy;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * A rudimentary wrapper around the Http Client which will inject the X-Ray trace header
 * to calls to AWS services.
 */
class XRayHttpClient implements HttpClientInterface
{
    private const TRACE_HEADER = 'X-Amzn-Trace-Id';

    public function __construct(private ?HttpClientInterface $client = null) {
        if (!$this->client) {
            $this->client = HttpClient::create();

            if (class_exists(RetryableHttpClient::class)) {
                $this->client = new RetryableHttpClient(
                    $this->client,
                    new AwsRetryStrategy(
                        AwsRetryStrategy::DEFAULT_RETRY_STATUS_CODES,
                        1000,
                        2.0,
                        0,
                        0.1,
                        new ChainAwsErrorFactory()
                    ),
                    3
                );
            }
        }
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if (
            !isset($options['headers'][self::TRACE_HEADER]) &&
            $traceId = getenv(TraceContext::TRACE_ENV_VAR)
        ) {
            $options['headers'][self::TRACE_HEADER] = $traceId;
        }

        return $this->client->request($method, $url, $options);
    }

    public function stream($responses, float $timeout = null): ResponseStreamInterface
    {
        return $this->client->stream($responses, $timeout);
    }

    public function withOptions(array $options): static
    {
        return new self($this->client->withOptions($options));
    }
}
