<?php

namespace Packages\Telemetry\Tests;

use Bref\Context\ContextBuilder;
use Packages\Telemetry\Enum\EnvironmentVariable;
use Packages\Telemetry\TraceContext;
use PHPUnit\Framework\TestCase;

class TraceContextTest extends TestCase
{
    public function testSetTraceHeaderFromEnvironment(): void
    {
        TraceContext::setTraceHeaderFromEnvironment();

        $this->assertEquals('fake-trace-id', getenv(EnvironmentVariable::X_AMZN_TRACE_ID->value));
    }

    public function testSetTraceHeaderFromContext(): void
    {
        $contextBuilder = (new ContextBuilder());
        $contextBuilder->setTraceId('fake-context-trace-id');

        $context = $contextBuilder->buildContext();

        TraceContext::setTraceHeaderFromContext($context);

        $this->assertEquals('fake-context-trace-id', getenv(EnvironmentVariable::X_AMZN_TRACE_ID->value));
    }
}
