<?php

namespace Packages\Models\Enum;

enum LineState: string
{
    case COVERED = 'covered';

    case PARTIAL = 'partial';

    case UNCOVERED = 'uncovered';
}
