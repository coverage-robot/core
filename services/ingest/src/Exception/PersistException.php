<?php

namespace App\Exception;

use RuntimeException;
use Throwable;

class PersistException extends RuntimeException
{
    public static function from(Throwable $exception): PersistException
    {
        return new PersistException(
            sprintf("An error occurred when persisting: %s.", $exception->getMessage()),
            $exception->getCode(),
            $exception
        );
    }
}