<?php

namespace App\Enum;

enum LineTypeEnum
{
    case UNKNOWN;
    case STATEMENT;
    case METHOD;
    case BRANCH;
}
