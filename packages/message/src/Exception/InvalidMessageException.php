<?php

declare(strict_types=1);

namespace Packages\Message\Exception;

use Packages\Contracts\PublishableMessage\PublishableMessageInterface;
use RuntimeException;
use Symfony\Component\Validator\ConstraintViolationListInterface;

final class InvalidMessageException extends RuntimeException
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
