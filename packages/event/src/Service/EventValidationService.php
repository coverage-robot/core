<?php

namespace Packages\Event\Service;

use Packages\Contracts\Event\EventInterface;
use Packages\Contracts\Event\InvalidEventException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class EventValidationService
{
    public function __construct(
        private readonly ValidatorInterface $validator
    ) {
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
