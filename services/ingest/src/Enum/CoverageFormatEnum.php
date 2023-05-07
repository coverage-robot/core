<?php

namespace App\Enum;

enum CoverageFormatEnum: string
{
    case LCOV = 'LCOV';

    case CLOVER = 'CLOVER';
}
