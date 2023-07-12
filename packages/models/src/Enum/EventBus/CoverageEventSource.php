<?php

namespace Packages\Models\Enum\EventBus;

enum CoverageEventSource: string
{
    case API = 'service.api';
    case INGEST = 'service.ingest';
    case ANALYSE = 'service.analyse';
}
