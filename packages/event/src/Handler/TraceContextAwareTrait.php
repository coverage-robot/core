<?php

namespace Handler;

use Bref\Context\Context;

trait TraceContextAwareTrait
{
    public function setTraceHeaderFromContext(Context $context): void
    {
        putenv('_X_AMZN_TRACE_ID=' . $context->getTraceId());
    }
}
