<?php

namespace Packages\Models\Enum;

enum EventType: string
{
    case JOB_STATE_CHANGE = 'JOB_STATE_CHANGE';

    case UPLOAD = 'UPLOAD';

    case COVERAGE_FINALISED = 'NEW_COVERAGE_FINALISED';
}
