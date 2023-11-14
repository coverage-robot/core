<?php

namespace Packages\Telemetry;

use Bref\Context\Context;

class TraceContext
{
    public const TRACE_ENV_VAR = '_X_AMZN_TRACE_ID';

    private const LAMBDA_INVOCATION_CONTEXT = 'LAMBDA_INVOCATION_CONTEXT';

    /**
     * For event-based handlers (i.e. not HTTP requests) the invocation context is
     * passed directly to the handler.
     *
     * This method extracts the trace ID and applies it directly to the environment
     * variables ready for use later.
     */
    public static function setTraceHeaderFromContext(Context $context): void
    {
        if (empty($context->getTraceId())) {
            return;
        }

        putenv(self::TRACE_ENV_VAR . '=' . $context->getTraceId());
    }

    /**
     * For FPM based requests (i.e. HTTP requests) the invocation context is persisted
     * into the environment variables as JSON. This method will unwrap the context and
     * extract the AWS X-Ray trace header.
     */
    public static function setTraceHeaderFromEnvironment(): void
    {
        if (!isset($_SERVER[self::LAMBDA_INVOCATION_CONTEXT])) {
            return;
        }

        /** @var array $context */
        $context = json_decode($_SERVER[self::LAMBDA_INVOCATION_CONTEXT], true);

        if (empty($context['traceId'] ?? '')) {
            return;
        }

        putenv(self::TRACE_ENV_VAR . '=' . (string)$context['traceId']);
    }
}
