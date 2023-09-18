<?php

namespace Packages\Models\Enum;

enum EventType: string
{
    case PIPELINE_COMPLETE = 'PIPELINE_COMPLETE';

    case UPLOAD = 'UPLOAD';
}
