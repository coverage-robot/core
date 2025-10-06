<?php

declare(strict_types=1);

namespace Packages\Clients\Client;

use Override;
use AsyncAws\Core\Input;
use AsyncAws\S3\Input\PutObjectRequest;
use AsyncAws\S3\Result\PutObjectOutput;
use AsyncAws\S3\S3Client;
use DateTimeImmutable;

final readonly class ObjectReferenceClient implements ObjectReferenceClientInterface
{
    public function __construct(
        private S3Client $client,
    ) {
    }

    #[Override]
    public function presign(Input $input, ?DateTimeImmutable $expires = null): string
    {
        return $this->client->presign($input, $expires);
    }

    #[Override]
    public function putObject(PutObjectRequest $input): PutObjectOutput
    {
        return $this->client->putObject($input);
    }
}
