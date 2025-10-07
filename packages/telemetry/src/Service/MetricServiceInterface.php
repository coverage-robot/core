<?php

declare(strict_types=1);

namespace Packages\Telemetry\Service;

use Packages\Telemetry\Enum\Resolution;
use Packages\Telemetry\Enum\Unit;

/**
 * A wrapper around the CloudWatch Embedded Metric Format (EMF) which allows custom
 * metrics to be pushed directly into CloudWatch for later analysis.
 */
interface MetricServiceInterface
{
    public const string FUNCTION_VERSION = 'functionVersion';

    public const string FUNCTION_NAME = 'functionName';

    /**
     * Put a new metric into CloudWatch.
     *
     * This uses EMF (Embedded Metric Format) to write a log line (as JSON) which Cloudwatch
     * will ingest and process.
     *
     * @param int|float|(int|float)[] $value
     * @param string[][]|null $dimensions
     */
    public function put(
        string $metric,
        int|float|array $value,
        Unit $unit,
        Resolution $resolution = Resolution::LOW,
        ?array $dimensions = null,
        array $properties = []
    ): void;

    /**
     * Increment a metric by a given value.
     *
     * @param string[][]|null $dimensions
     */
    public function increment(
        string $metric,
        int $value = 1,
        Resolution $resolution = Resolution::LOW,
        ?array $dimensions = null,
        array $properties = []
    ): void;
}
