<?php

namespace Packages\Contracts\Environment;

enum Service: string
{
    case API = 'api';
    case ANALYSE = 'analyse';
    case PUBLISH = 'publish';
    case ORCHESTRATOR = 'orchestrator';
    case INGEST = 'ingest';
}
