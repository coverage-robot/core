<?php

namespace Packages\Clients\Tests\Client;

use AsyncAws\Core\Input;
use AsyncAws\S3\Input\PutObjectRequest;
use AsyncAws\S3\Result\PutObjectOutput;
use AsyncAws\S3\S3Client;
use DateTimeImmutable;

final class ObjectReferenceClient implements ObjectReferenceClientInterface
{
    public function __construct(
        private readonly S3Client $client,
    ) {
    }

    public function presign(Input $input, ?DateTimeImmutable $expires = null): string
    {
        return $this->client->presign($input, $expires);
    }

    public function putObject(PutObjectRequest $input): PutObjectOutput
    {
        return $this->client->putObject($input);
    }
}
