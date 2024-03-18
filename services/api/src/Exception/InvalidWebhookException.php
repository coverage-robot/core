<?php

namespace App\Exception;

use App\Model\Webhook\WebhookInterface;
use RuntimeException;
use Symfony\Component\Validator\ConstraintViolationListInterface;

final class InvalidWebhookException extends RuntimeException
{
    public function __construct(
        private readonly WebhookInterface $webhook,
        private readonly ConstraintViolationListInterface $violations
    ) {
        parent::__construct(sprintf('Webhook %s validation with errors.', $webhook));
    }

    public static function constraintViolations(
        WebhookInterface $webhook,
        ConstraintViolationListInterface $errors
    ): self {
        return new self($webhook, $errors);
    }

    public function getWebhook(): WebhookInterface
    {
        return $this->webhook;
    }

    public function getViolations(): ConstraintViolationListInterface
    {
        return $this->violations;
    }
}
