<?php

namespace App\Service;

use App\Model\SignedUrl;
use AsyncAws\S3\Input\PutObjectRequest;
use AsyncAws\S3\S3Client;
use DateTimeImmutable;

class UploadSignerService
{
    public function __construct(
        private readonly S3Client $s3Client
    ) {
    }

    public function sign(string $uploadId, PutObjectRequest $input, DateTimeImmutable $expiry): SignedUrl
    {
        return new SignedUrl(
            $uploadId,
            $this->signS3Request($input, $expiry),
            $expiry
        );
    }

    /**
     * Sign the S3 PUT request, so that it can be returned, and then used to
     * upload the coverage file to S3.
     */
    private function signS3Request(PutObjectRequest $input, DateTimeImmutable $expiry): string
    {
        return $this->s3Client->presign(
            $input,
            $expiry,
        );
    }
}
