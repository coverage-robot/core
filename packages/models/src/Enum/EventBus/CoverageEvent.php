<?php

namespace Packages\Models\Enum\EventBus;

enum CoverageEvent: string
{
    case INGEST_SUCCESS = 'INGEST_SUCCESS';
    case INGEST_FAILURE = 'INGEST_FAILURE';
    case ANALYSIS_ON_NEW_UPLOAD_SUCCESS = 'ANALYSIS_ON_NEW_UPLOAD_SUCCESS';
    case ANALYSE_FAILURE = 'ANALYSE_FAILURE';
    case JOB_STATE_CHANGE = 'JOB_STATE_CHANGE';
}
