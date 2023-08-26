<?php

namespace Packages\Models\Enum;

enum LineType: string
{
    case STATEMENT = 'STATEMENT';
    case METHOD = 'METHOD';
    case BRANCH = 'BRANCH';
}
