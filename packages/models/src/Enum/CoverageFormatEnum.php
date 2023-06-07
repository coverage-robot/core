<?php

namespace Packages\Models\Enum;

enum CoverageFormatEnum: string
{
    case LCOV = 'LCOV';

    case CLOVER = 'CLOVER';
}
