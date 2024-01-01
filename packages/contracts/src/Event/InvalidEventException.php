<?php

namespace Packages\Contracts\Event;

use RuntimeException;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class InvalidEventException extends RuntimeException
{
    public static function constraintViolations(
        EventInterface $event,
        ConstraintViolationListInterface $errors
    ): self {
        return new self(
            sprintf(
                'Event %s failed validation with errors: %s',
                $event,
                (string)$errors
            ),
            0
        );
    }
}
