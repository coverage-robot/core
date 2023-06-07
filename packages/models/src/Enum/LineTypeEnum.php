<?php

namespace Packages\Models\Enum;

enum LineTypeEnum: string
{
    case UNKNOWN = 'UNKNOWN';
    case STATEMENT = 'STATEMENT';
    case METHOD = 'METHOD';
    case BRANCH = 'BRANCH';
}
