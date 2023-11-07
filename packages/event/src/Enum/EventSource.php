<?php

namespace Packages\Event\Enum;

enum EventSource: string
{
    case API = 'service.api';
    case INGEST = 'service.ingest';
    case ANALYSE = 'service.analyse';
    case ORCHESTRATOR = 'service.orchestrator';
}
