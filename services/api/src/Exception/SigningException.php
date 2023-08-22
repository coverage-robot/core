<?php

namespace App\Exception;

use Exception;
use Throwable;

class SigningException extends Exception
{
    public static function invalidParameters(?Throwable $exception = null): self
    {
        return new self(
            'Parameters provided for signing do not match expectation.',
            0,
            $exception
        );
    }
}
