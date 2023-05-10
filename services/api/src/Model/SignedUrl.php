<?php

namespace App\Model;

use DateTimeInterface;
use JsonSerializable;

class SignedUrl implements JsonSerializable
{
    public function __construct(
        public readonly string $signedUrl,
        public readonly DateTimeInterface $expiration
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'signedUrl' => $this->signedUrl,
            'expiration' => $this->expiration->format(DateTimeInterface::ATOM)
        ];
    }
}
