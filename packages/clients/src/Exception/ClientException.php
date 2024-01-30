<?php

namespace Packages\Clients\Exception;

use Exception;
use Throwable;

final class ClientException extends Exception
{
    public static function authenticationException(?Throwable $previous): self
    {
        return new self('Unable to authenticate using the client.', 0, $previous);
    }
}
