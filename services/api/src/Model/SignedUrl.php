<?php

namespace App\Model;

use DateTimeInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class SignedUrl
{
    public function __construct(
        #[Assert\NotBlank]
        private readonly string $uploadId,
        #[Assert\Url]
        private readonly string $signedUrl,
        #[Assert\GreaterThan('now')]
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
