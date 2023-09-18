<?php

namespace App\Model;

use DateTimeInterface;

class SignedUrl
{
    public function __construct(
        private readonly string $uploadId,
        private readonly string $signedUrl,
        private readonly DateTimeInterface $expiration
    ) {
    }

    public function getUploadId(): string
    {
        return $this->uploadId;
    }

    public function getSignedUrl(): string
    {
        return $this->signedUrl;
    }

    public function getExpiration(): DateTimeInterface
    {
        return $this->expiration;
    }
}
