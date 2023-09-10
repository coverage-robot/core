<?php

namespace App\Exception;

use Exception;

class AuthenticationException extends Exception
{
    public static function invalidProvider(): self
    {
        return new self('The provider is invalid.');
    }

    /**
     * @param Exception|null $exception
     * @return self
     */
    public static function invalidUploadToken(): self
    {
        return new self('The provided upload token is invalid.');
    }

    public static function invalidGraphToken(): self
    {
        return new self('The provided graph token is invalid.');
    }

    public static function invalidSignature(): self
    {
        return new self('The provided signature is invalid.');
    }
}
