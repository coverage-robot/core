<?php

namespace App\Service;

use App\Client\PresignableClientInterface;
use App\Model\SignedUrl;
use AsyncAws\S3\Input\PutObjectRequest;
use AsyncAws\S3\S3Client;
use DateTimeImmutable;

class UploadSignerService
{
    /**
     * @param PresignableClientInterface&S3Client $client
     */
    public function __construct(
        private readonly S3Client $client
    ) {
    }

    public function sign(string $uploadId, PutObjectRequest $input, DateTimeImmutable $expiry): SignedUrl
    {
        return new SignedUrl(
            $uploadId,
            $this->signRequest($input, $expiry),
            $expiry
        );
    }

    /**
     * Sign the S3 PUT request, so that it can be returned, and then used to
     * upload the coverage file to S3.
     */
    private function signRequest(PutObjectRequest $input, DateTimeImmutable $expiry): string
    {
        return $this->client->presign(
            $input,
            $expiry,
        );
    }
}
