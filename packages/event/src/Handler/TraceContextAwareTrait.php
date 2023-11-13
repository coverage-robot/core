<?php

namespace Packages\Event\Handler;

use Bref\Context\Context;

trait TraceContextAwareTrait
{
    private const TRACE_HEADER = '_X_AMZN_TRACE_ID';

    private const LAMBDA_INVOCATION_CONTEXT = 'LAMBDA_INVOCATION_CONTEXT';

    /**
     * For event-based handlers (i.e. not HTTP requests) the invocation context is
     * passed directly to the handler.
     *
     * This method extracts the trace ID and applies it directly to the environment
     * variables ready for use later.
     */
    public function setTraceHeaderFromContext(Context $context): void
    {
        if (empty($context->getTraceId())) {
            return;
        }

        putenv(self::TRACE_HEADER . '=' . $context->getTraceId());
    }

    /**
     * For FPM based requests (i.e. HTTP requests) the invocation context is persisted
     * into the environment variables as JSON. This method will unwrap the context and
     * extract the AWS X-Ray trace header.
     */
    public function setTraceHeaderFromEnvironment(): void
    {
        if (!isset($_SERVER['LAMBDA_INVOCATION_CONTEXT'])) {
            return;
        }

        $context = json_decode($_SERVER['LAMBDA_INVOCATION_CONTEXT'], true);

        if (empty($context['traceId'] ?? '')) {
            return;
        }

        putenv(self::TRACE_HEADER . '=' . $context['traceId']);
    }
}
