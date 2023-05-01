<?php

namespace App\Enum;

enum LineTypeEnum: string
{
    case UNKNOWN = "UNKNOWN";
    case STATEMENT = "STATEMENT";
    case METHOD = "METHOD";
    case BRANCH = "BRANCH";
}
