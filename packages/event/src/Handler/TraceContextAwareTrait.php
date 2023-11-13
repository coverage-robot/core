<?php

namespace Packages\Event\Handler;

use Bref\Context\Context;

trait TraceContextAwareTrait
{
    private const TRACE_HEADER = '_X_AMZN_TRACE_ID';

    public function setTraceHeaderFromContext(Context $context): void
    {
        putenv(self::TRACE_HEADER . '=' . $context->getTraceId());
    }
}
