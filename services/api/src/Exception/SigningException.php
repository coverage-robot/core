<?php

namespace App\Exception;

use Exception;

class SigningException extends Exception
{
    /**
     * @param Exception|null $exception
     * @return self
     */
    public static function invalidParameters(?Exception $exception = null): self
    {
        return new self(
            'Parameters provided for signing do not match expectation.',
            0,
            $exception
        );
    }
}
