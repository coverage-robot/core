<?php

use App\Enum\EnvironmentVariable;
use App\Kernel;

require_once dirname(__DIR__) . '/vendor/autoload_runtime.php';

return function (array $context) {
    // Apply the trace ID from the Lambda invocation context to the environment variables
    // so that it can be used later in the call stack (i.e. triggering events, etc)
    $lambdaContext = json_decode($_SERVER['LAMBDA_INVOCATION_CONTEXT'], true);
    putenv(EnvironmentVariable::TRACE_ID->value . '=' . $lambdaContext['traceId'] ?? '');

    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
