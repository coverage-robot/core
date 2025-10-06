<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\InvalidWebhookException;
use App\Model\Webhook\WebhookInterface;
use Packages\Message\Exception\InvalidMessageException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class WebhookValidationService
{
    public function __construct(
        private ValidatorInterface $validator
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

        throw InvalidWebhookException::constraintViolations($webhook, $errors);
    }
}
