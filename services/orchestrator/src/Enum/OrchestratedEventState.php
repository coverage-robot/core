<?php

namespace App\Enum;

enum OrchestratedEventState: string
{
    case ONGOING = 'IN_PROGRESS';
    case SUCCESS = 'SUCCESS';
    case FAILURE = 'FAILURE';
}
