<?php

namespace Packages\Contracts\PublishableMessage;

use RuntimeException;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class InvalidMessageException extends RuntimeException
{
    public static function constraintViolations(
        PublishableMessageInterface $message,
        ConstraintViolationListInterface $errors
    ): self {
        return new self(
            sprintf(
                'Message %s failed validation with errors: %s',
                $message,
                (string)$errors
            ),
            0
        );
    }
}
