<?php

namespace Packages\Clients\Tests\Client;

use AsyncAws\Core\Input;
use AsyncAws\S3\Input\PutObjectRequest;
use AsyncAws\S3\Result\PutObjectOutput;
use DateTimeImmutable;

interface ObjectReferenceClientInterface
{
    public function presign(Input $input, ?DateTimeImmutable $expires = null): string;

    public function putObject(PutObjectRequest $input): PutObjectOutput;
}
