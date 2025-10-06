<?php

declare(strict_types=1);

namespace Packages\Message\Service;

use Packages\Contracts\PublishableMessage\PublishableMessageInterface;
use Packages\Message\Exception\InvalidMessageException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class MessageValidationService
{
    public function __construct(
        private ValidatorInterface $validator
    ) {
    }

    /**
     * @throws InvalidMessageException
     */
    public function validate(PublishableMessageInterface $message): true
    {
        $errors = $this->validator->validate($message);

        if ($errors->count() === 0) {
            return true;
        }

        throw InvalidMessageException::constraintViolations(
            $message,
            $errors
        );
    }
}
