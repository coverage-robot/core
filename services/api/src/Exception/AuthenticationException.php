<?php

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class AuthenticationException extends HttpException
{
    public static function invalidUploadToken(): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            'The provided upload token is invalid.'
        );
    }

    public static function invalidGraphToken(): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            'The provided graph token is invalid.'
        );
    }
}
