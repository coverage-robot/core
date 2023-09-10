<?php

namespace App\Enum;

enum WebhookState: string
{
    /**
     * A webhook that indicates some resource in the VCS provider has been created.
     */
    case CREATED = 'created';

    /**
     * A webhook that indicates some resource in the VCS provider has been completed.
     *
     * This does not necessarily mean that the resource has been successful, only that
     * it has finished.
     */
    case COMPLETED = 'completed';
}
