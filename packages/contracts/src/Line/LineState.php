<?php

declare(strict_types=1);

namespace Packages\Contracts\Line;

enum LineState: string
{
    case COVERED = 'covered';

    case PARTIAL = 'partial';

    case UNCOVERED = 'uncovered';
}
