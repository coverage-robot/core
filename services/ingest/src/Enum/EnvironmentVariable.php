<?php

declare(strict_types=1);

namespace App\Enum;

enum EnvironmentVariable: string
{
    case BIGQUERY_PROJECT = 'BIGQUERY_PROJECT';
    case BIGQUERY_ENVIRONMENT_DATASET = 'BIGQUERY_ENVIRONMENT_DATASET';
    case BIGQUERY_LINE_COVERAGE_TABLE = 'BIGQUERY_LINE_COVERAGE_TABLE';
    case BIGQUERY_UPLOAD_TABLE = 'BIGQUERY_UPLOAD_TABLE';
}
