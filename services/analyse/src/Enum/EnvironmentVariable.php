<?php

namespace App\Enum;

enum EnvironmentVariable: string
{
    case GITHUB_APP_ID = 'GITHUB_APP_ID';
    case GITHUB_BOT_ID = 'GITHUB_BOT_ID';

    case BIGQUERY_PROJECT = 'BIGQUERY_PROJECT';
    case BIGQUERY_ENVIRONMENT_DATASET = 'BIGQUERY_ENVIRONMENT_DATASET';
    case BIGQUERY_LINE_COVERAGE_TABLE = 'BIGQUERY_LINE_COVERAGE_TABLE';

    case EVENT_BUS = 'EVENT_BUS';
}
