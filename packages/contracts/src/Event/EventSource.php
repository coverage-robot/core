<?php

namespace Packages\Contracts\Event;

enum EventSource: string
{
    case API = 'service.api';
    case INGEST = 'service.ingest';
    case ANALYSE = 'service.analyse';
    case ORCHESTRATOR = 'service.orchestrator';
    case PUBLISH = 'service.publish';
}
