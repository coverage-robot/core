<?php

namespace App\Exception;

use Exception;

class QueryException extends Exception
{
    public static function typeMismatch(string $receivedType, string $expectedType): self
    {
        return new self(
            sprintf(
                'Expected query to return an %s but instead received %s.',
                $expectedType,
                $receivedType
            )
        );
    }
}
