<?php

namespace App\Service;

use App\Model\SignedUrl;
use AsyncAws\S3\Input\PutObjectRequest;
use DateTimeImmutable;

interface UploadSignerServiceInterface
{
    public function sign(string $uploadId, PutObjectRequest $input, DateTimeImmutable $expiry): SignedUrl;
}
