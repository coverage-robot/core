<?php

declare(strict_types=1);

namespace Packages\Telemetry\Service;

use Bref\Context\Context;
use Packages\Telemetry\Enum\EnvironmentVariable;

final class TraceContext
{
    private const string LAMBDA_INVOCATION_CONTEXT = 'LAMBDA_INVOCATION_CONTEXT';

    /**
     * For event-based handlers (i.e. not HTTP requests) the invocation context is
     * passed directly to the handler.
     *
     * This method extracts the trace ID and applies it directly to the environment
     * variables ready for use later.
     */
    public static function setTraceHeaderFromContext(Context $context): void
    {
        if ($context->getTraceId() === '' || $context->getTraceId() === '0') {
            return;
        }

        putenv(EnvironmentVariable::X_AMZN_TRACE_ID->value . '=' . $context->getTraceId());
    }

    /**
     * For FPM based requests (i.e. HTTP requests) the invocation context is persisted
     * into the environment variables as JSON. This method will unwrap the context and
     * extract the AWS X-Ray trace header.
     */
    public static function setTraceHeaderFromEnvironment(): void
    {
        if (!array_key_exists(self::LAMBDA_INVOCATION_CONTEXT, $_SERVER)) {
            return;
        }

        /** @var mixed $rawContext */
        $rawContext = $_SERVER[self::LAMBDA_INVOCATION_CONTEXT];

        if (!is_string($rawContext)) {
            return;
        }

        /** @var array $context */
        $context = json_decode($rawContext, true);

        /** @var string $traceId */
        $traceId = ($context['traceId'] ?? '');

        if ($traceId === "") {
            return;
        }

        putenv(EnvironmentVariable::X_AMZN_TRACE_ID->value . '=' . $traceId);
    }
}
