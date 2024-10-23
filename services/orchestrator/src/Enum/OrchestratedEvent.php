<?php

declare(strict_types=1);

namespace App\Enum;

enum OrchestratedEvent: string
{
    case JOB = 'JOB';

    case INGESTION = 'INGESTION';

    case FINALISED = 'FINALISED';
}
