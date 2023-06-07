<?php

namespace Packages\Models\Enum;

enum LineStateEnum: string
{
    case COVERED = 'covered';

    case PARTIAL = 'partial';

    case UNCOVERED = 'uncovered';
}
