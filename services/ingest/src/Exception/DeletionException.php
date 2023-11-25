<?php

namespace App\Exception;

use Exception;
use Throwable;

class DeletionException extends Exception
{
    public static function from(Throwable $exception): DeletionException
    {
        return new DeletionException(
            sprintf('An error occurred when deleting: %s.', $exception->getMessage()),
            (int) $exception->getCode(),
            $exception
        );
    }
}
