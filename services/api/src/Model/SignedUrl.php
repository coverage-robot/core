<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeInterface;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class SignedUrl
{
    public function __construct(
        #[Assert\NotBlank]
        private string $uploadId,
        #[Assert\Url]
        private string $signedUrl,
        #[Assert\GreaterThan('now')]
        private DateTimeInterface $expiration
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
