<?php

namespace Packages\Models\Enum;

enum PublishableCheckRunStatus: string
{
    case IN_PROGRESS = 'in_progress';
    case SUCCESS = 'success';
    case FAILURE = 'failure';
}
