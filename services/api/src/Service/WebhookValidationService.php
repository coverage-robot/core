<?php

namespace App\Service;

use App\Exception\InvalidWebhookException;
use App\Model\Webhook\WebhookInterface;
use Packages\Contracts\PublishableMessage\InvalidMessageException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class WebhookValidationService
{
    public function __construct(
        private readonly ValidatorInterface $validator
    ) {
    }

    /**
     * @throws InvalidMessageException
     */
    public function validate(WebhookInterface $webhook): true
    {
        $errors = $this->validator->validate($webhook);

        if ($errors->count() === 0) {
            return true;
        }

        throw InvalidWebhookException::constraintViolations(
            $webhook,
            $errors
        );
    }
}
