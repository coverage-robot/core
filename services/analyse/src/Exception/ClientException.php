<?php

namespace App\Exception;

use RuntimeException;
use Throwable;

class ClientException extends RuntimeException
{
    public static function authenticationException(?Throwable $previous): ClientException
    {
        return new ClientException('Unable to authenticate using the client.', 0, $previous);
    }
}
