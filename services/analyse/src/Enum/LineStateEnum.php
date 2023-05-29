<?php

namespace App\Enum;

enum LineStateEnum: string
{
    case COVERED = "covered";

    case PARTIAL = "partial";

    case UNCOVERED = "uncovered";
}
