<?php

namespace Packages\Telemetry\Service;

use Packages\Telemetry\Enum\Resolution;
use Packages\Telemetry\Enum\Unit;

/**
 * A wrapper around the CloudWatch Embedded Metric Format (EMF) which allows custom
 * metrics to be pushed directly into CloudWatch for later analysis.
 */
interface MetricServiceInterface
{
    public const FUNCTION_VERSION = 'functionVersion';

    public const FUNCTION_NAME = 'functionName';

    /**
     * Put a new metric into CloudWatch.
     *
     * This uses EMF (Embedded Metric Format) to write a log line (as JSON) which Cloudwatch
     * will ingest and process.
     *
     * @param int|float|(int|float)[] $value
     */
    public function put(
        string $metric,
        int|float|array $value,
        Unit $unit,
        Resolution $resolution = Resolution::LOW,
        ?array $dimensions = null,
        array $properties = []
    ): void;
}
