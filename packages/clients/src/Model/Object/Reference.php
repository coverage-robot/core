<?php

namespace Packages\Clients\Model\Object;

use DateTimeInterface;
use Stringable;

class Reference implements Stringable
{
    public function __construct(
        private readonly string $path,
        private readonly string $signedUrl,
        private readonly DateTimeInterface $expiration,
    ) {
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getSignedUrl(): string
    {
        return $this->signedUrl;
    }

    public function getExpiration(): DateTimeInterface
    {
        return $this->expiration;
    }

    public function __toString(): string
    {
        return sprintf(
            'ObjectReference#%s',
            $this->path
        );
    }
}
