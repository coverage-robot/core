<?php

namespace Packages\Message\Service;

use Packages\Contracts\PublishableMessage\PublishableMessageInterface;
use Packages\Message\Exception\InvalidMessageException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class MessageValidationService
{
    public function __construct(
        private readonly ValidatorInterface $validator
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
