<?php

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class SigningException extends HttpException
{
    public static function invalidSignature(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            'The signature provided is invalid.'
        );
    }
}
