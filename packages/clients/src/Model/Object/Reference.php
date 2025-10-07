<?php

declare(strict_types=1);

namespace Packages\Clients\Model\Object;

use Override;
use DateTimeInterface;
use Stringable;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class Reference implements Stringable
{
    public function __construct(
        #[Assert\NotBlank]
        private string $path,
        #[Assert\NotBlank]
        private string $signedUrl,
        private DateTimeInterface $expiration,
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

    #[Override]
    public function __toString(): string
    {
        return sprintf(
            'ObjectReference#%s',
            $this->path
        );
    }
}
