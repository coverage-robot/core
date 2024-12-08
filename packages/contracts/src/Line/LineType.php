<?php

declare(strict_types=1);

namespace Packages\Contracts\Line;

enum LineType: string
{
    case STATEMENT = 'STATEMENT';
    case METHOD = 'METHOD';
    case BRANCH = 'BRANCH';
}
