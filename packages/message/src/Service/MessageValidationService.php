<?php

namespace Packages\Message\Service;

use Packages\Contracts\PublishableMessage\InvalidMessageException;
use Packages\Contracts\PublishableMessage\PublishableMessageInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MessageValidationService
{
    private ValidatorInterface $validator;

    public function __construct()
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
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
