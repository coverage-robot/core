<?php

namespace Packages\Models\Enum;

enum EventType: string
{
    case PIPELINE_STARTED = 'PIPELINE_STARTED';

    case PIPELINE_COMPLETE = 'PIPELINE_COMPLETE';

    case UPLOAD = 'UPLOAD';
}
