<?php

namespace App\Exception;

use RuntimeException;
use Throwable;

final class RetrievalException extends RuntimeException
{
    public static function from(Throwable $exception): RetrievalException
    {
        return new RetrievalException(
            sprintf('An error occurred when retrieving: %s.', $exception->getMessage()),
            (int) $exception->getCode(),
            $exception
        );
    }
}
