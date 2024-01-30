<?php

namespace App\Exception;

use App\Enum\QueryParameter;
use Exception;

final class QueryException extends Exception
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

    public static function invalidParameters(QueryParameter $parameter): self
    {
        return new self(sprintf('Invalid parameter %s.', $parameter->name));
    }

    public static function invalidQueryResult(): self
    {
        return new self('Query result is invalid.');
    }
}
