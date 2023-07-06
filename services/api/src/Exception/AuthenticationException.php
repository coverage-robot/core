<?php

namespace Exception;

use Exception;

class AuthenticationException extends Exception
{
    /**
     * @param Exception|null $exception
     * @return self
     */
    public static function invalidProjectToken(): self
    {
        return new self('The provided project token is invalid.');
    }

    public static function failedToCreateProjectToken(int $attempts): self
    {
        return new self(
            sprintf('Failed to generate project token after %s attempts.', $attempts)
        );
    }
}
