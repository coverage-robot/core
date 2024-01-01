<?php

namespace Packages\Message\Service;

use Packages\Contracts\PublishableMessage\InvalidMessageException;
use Packages\Contracts\PublishableMessage\PublishableMessageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MessageValidationService
{
    public function __construct(
        private readonly LoggerInterface $messageValidationLogger,
        private readonly ?ValidatorInterface $validator = null
    ) {
    }

    /**
     * @throws InvalidMessageException
     */
    public function validate(PublishableMessageInterface $message): true
    {
        if (!$this->validator) {
            $this->messageValidationLogger->warning(
                'Validation of message %s was skipped because no validator was provided.',
                [
                    'message' => $message,
                ]
            );

            return true;
        }

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
