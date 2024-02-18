<?php

namespace Packages\Event\Exception;

use Packages\Contracts\Event\EventInterface;
use RuntimeException;
use Symfony\Component\Validator\ConstraintViolationListInterface;

final class InvalidEventException extends RuntimeException
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
