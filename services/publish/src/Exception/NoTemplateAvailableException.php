<?php

namespace App\Exception;

use RuntimeException;

final class NoTemplateAvailableException extends RuntimeException
{
    public function __construct(object $object)
    {
        parent::__construct(
            sprintf(
                'No template available for rendering message of type: %s.',
                $object::class
            )
        );
    }
}
