<?php

namespace App\Exception;

use Exception;

final class TokenException extends Exception
{
    public static function failedToCreateToken(int $attempts): self
    {
        return new self(
            sprintf('Failed to generate project token after %s attempts.', $attempts)
        );
    }
}
