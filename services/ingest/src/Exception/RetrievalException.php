<?php

namespace App\Exception;

use RuntimeException;
use Throwable;

class RetrievalException extends RuntimeException
{
    public static function from(Throwable $exception): RetrievalException
    {
        return new RetrievalException(
            sprintf("An error occurred when retrieving: %s.", $exception->getMessage()),
            $exception->getCode(),
            $exception
        );
    }
}