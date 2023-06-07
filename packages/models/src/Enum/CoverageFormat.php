<?php

namespace Packages\Models\Enum;

enum CoverageFormat: string
{
    case LCOV = 'LCOV';

    case CLOVER = 'CLOVER';
}
