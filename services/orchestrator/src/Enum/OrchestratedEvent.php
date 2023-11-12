<?php

namespace App\Enum;

enum OrchestratedEvent: string
{
    case JOB = 'JOB';

    case INGESTION = 'INGESTION';

    case FINALISED = 'FINALISED';
}
