<?php

namespace Packages\Contracts\Environment;

enum Service: string
{
    case API = 'api';
    case ANALYSE = 'analyse';
    case PUBLUSH = 'publish';
    case ORCHESTRATOR = 'orchestrator';
    case INGEST = 'ingest';
}
