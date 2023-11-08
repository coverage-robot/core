<?php

namespace App\Enum;

enum EnvironmentVariable: string
{
    case EVENT_STORE = 'EVENT_STORE';

    case EVENT_BUS = 'EVENT_BUS';
}
