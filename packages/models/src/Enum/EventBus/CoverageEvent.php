<?php

namespace Packages\Models\Enum\EventBus;

enum CoverageEvent: string
{
    case INGEST_SUCCESS = 'INGEST_SUCCESS';
    case INGEST_FAILURE = 'INGEST_FAILURE';
    case ANALYSE_SUCCESS = 'ANALYSE_SUCCESS';
    case ANALYSE_FAILURE = 'ANALYSE_FAILURE';
    case PIPELINE_COMPLETE = 'PIPELINE_COMPLETE';
}
