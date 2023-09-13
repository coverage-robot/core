<?php

namespace App\Enum;

enum JobState: string
{
    case SUCCESS = 'success';
    case FAILURE = 'failure';
    case NEUTRAL = 'neutral';
    case CANCELLED = 'cancelled';
    case TIMED_OUT = 'timed_out';
    case ACTION_REQUIRED = 'action_required';
    case STALE = 'stale';
    case PENDING = 'pending';
    case STARTUP_FAILURE = 'startup_failure';
    case WAITING = 'waiting';
    case SKIPPED = 'skipped';
}
