<?php

namespace App\Exception;

use Packages\Contracts\PublishableMessage\PublishableMessageInterface;
use RuntimeException;

class PublishingNotSupportedException extends RuntimeException
{
    public function __construct(string $publisher, PublishableMessageInterface $publishableMessage)
    {
        parent::__construct(
            sprintf(
                'Publishing %s using %s is not supported',
                $publisher,
                $publishableMessage::class,
            )
        );
    }
}
