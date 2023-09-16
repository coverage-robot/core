<?php

namespace App\Enum;

enum JobState: string
{
    case COMPLETED = 'completed';
    case IN_PROGRESS = 'in_progress';
    case PENDING = 'pending';
    case QUEUED = 'queued';
}
