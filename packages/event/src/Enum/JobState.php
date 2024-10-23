<?php

declare(strict_types=1);

namespace Packages\Event\Enum;

enum JobState: string
{
    case COMPLETED = 'completed';
    case IN_PROGRESS = 'in_progress';
    case PENDING = 'pending';
    case QUEUED = 'queued';
}
