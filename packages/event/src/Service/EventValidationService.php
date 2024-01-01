<?php

namespace Packages\Event\Service;

use Packages\Contracts\Event\EventInterface;
use Packages\Contracts\Event\InvalidEventException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class EventValidationService
{
    public function __construct(
        private readonly LoggerInterface $eventValidationLogger,
        private readonly ?ValidatorInterface $validator = null
    ) {
    }

    /**
     * @throws InvalidEventException
     */
    public function validate(EventInterface $event): true
    {
        if (!$this->validator) {
            $this->eventValidationLogger->warning(
                'Validation of event %s was skipped because no validator was provided.',
                [
                    'message' => $event,
                ]
            );

            return true;
        }

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
