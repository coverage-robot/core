<?php

namespace App\Enum;

enum EnvironmentVariable: string
{
    case WEBHOOK_SECRET = 'WEBHOOK_SECRET';
    case EVENT_BUS = 'EVENT_BUS';
}
