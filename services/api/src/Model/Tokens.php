<?php

declare(strict_types=1);

namespace App\Model;

final readonly class Tokens
{
    public function __construct(
        private string $uploadToken,
        private string $graphToken
    ) {
    }

    public function getUploadToken(): string
    {
        return $this->uploadToken;
    }

    public function getGraphToken(): string
    {
        return $this->graphToken;
    }
}
