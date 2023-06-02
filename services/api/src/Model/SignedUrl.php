<?php

namespace App\Model;

use DateTimeInterface;
use JsonSerializable;

class SignedUrl implements JsonSerializable
{
    public function __construct(
        private readonly string $signedUrl,
        private readonly DateTimeInterface $expiration
    ) {
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
            'signedUrl' => $this->getSignedUrl(),
            'expiration' => $this->getExpiration()->format(DateTimeInterface::ATOM)
        ];
    }
}
