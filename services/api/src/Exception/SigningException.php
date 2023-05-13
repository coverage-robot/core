<?php

namespace App\Exception;

use Exception;

class SigningException extends Exception
{
    /**
     * @param array<array-key, string> $missingFields
     * @return self
     */
    public static function invalidPayload(array $missingFields): self
    {
        return new self('Invalid payload. Missing fields: ' . implode(', ', $missingFields) . '.');
    }
}
