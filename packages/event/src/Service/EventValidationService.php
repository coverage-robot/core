<?php

namespace Packages\Event\Service;

use Packages\Contracts\Event\EventInterface;
use Packages\Contracts\Event\InvalidEventException;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class EventValidationService
{
    private ValidatorInterface $validator;

    public function __construct()
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    /**
     * @throws InvalidEventException
     */
    public function validate(EventInterface $event): true
    {
        $errors = $this->validator->validate($event);

        if ($errors->count() === 0) {
            return true;
        }

        throw InvalidEventException::constraintViolations(
            $event,
            $errors
        );
    }
}
