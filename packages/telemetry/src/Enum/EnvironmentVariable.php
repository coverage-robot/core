<?php

namespace Packages\Telemetry\Enum;

enum EnvironmentVariable: string
{
    case AWS_LAMBDA_FUNCTION_VERSION = 'AWS_LAMBDA_FUNCTION_VERSION';
    case AWS_LAMBDA_FUNCTION_NAME = 'AWS_LAMBDA_FUNCTION_NAME';
    case X_AMZN_TRACE_ID = '_X_AMZN_TRACE_ID';
}
