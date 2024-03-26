<?php

namespace App\Model;

final class Tokens
{
    public function __construct(
        private readonly string $uploadToken,
        private readonly string $graphToken
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
