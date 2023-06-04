<?php

namespace App\Model;

use DateTimeInterface;
use JsonSerializable;

class SignedUrl implements JsonSerializable
{
    public function __construct(
        private readonly string $signedUrl,
        private readonly string $uploadId,
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

    public function jsonSerialize(): array
    {
        return [
            'uploadId' => $this->getUploadId(),
            'signedUrl' => $this->getSignedUrl(),
            'expiration' => $this->getExpiration()->format(DateTimeInterface::ATOM)
        ];
    }
}
