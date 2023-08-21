<?php

namespace App\Exception;

use Exception;
use Throwable;

class GraphException extends Exception
{
    /**
     * @param Exception|null $exception
     * @return self
     */
    public static function invalidParameters(?Throwable $exception = null): self
    {
        return new self(
            'Parameters provided for graphing do not match expectation.',
            0,
            $exception
        );
    }
}
