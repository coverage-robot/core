<?php

namespace App\Exception;

use App\Model\Webhook\WebhookInterface;
use RuntimeException;
use Symfony\Component\Validator\ConstraintViolationListInterface;

final class InvalidWebhookException extends RuntimeException
{
    public static function constraintViolations(
        WebhookInterface $webhook,
        ConstraintViolationListInterface $errors
    ): self {
        return new self(
            sprintf(
                'Webhook %s failed validation with errors: %s',
                $webhook,
                (string)$errors
            ),
            0
        );
    }
}
