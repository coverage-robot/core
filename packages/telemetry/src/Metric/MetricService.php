<?php

namespace Packages\Telemetry\Metric;

use Packages\Telemetry\Enum\Resolution;
use Packages\Telemetry\Enum\Unit;
use Packages\Telemetry\Model\Metric\Metadata;
use Packages\Telemetry\Model\Metric\MetricDefinition;
use Packages\Telemetry\Model\Metric\MetricDirective;
use Packages\Telemetry\Model\Metric\RootNode;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * A wrapper around the CloudWatch Embedded Metric Format (EMF) which allows custom
 * metrics to be pushed directly into CloudWatch for later analysis.
 */
class MetricService
{
    private const NAMESPACE = 'Metrics';
    private const FUNCTION_VERSION = 'functionVersion';
    private const FUNCTION_NAME = 'functionName';

    public function __construct(
        private readonly LoggerInterface $metricsLogger,
        private readonly ClockInterface $clock,
        private readonly SerializerInterface&NormalizerInterface $serializer
    ) {
    }

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
    ): void {
        try {
            $embeddedMetric = $this->serializer->serialize(
                $this->getAsEmbeddedMetric(
                    self::NAMESPACE,
                    $metric,
                    $value,
                    $this->clock->now()
                        ->getTimestamp(),
                    $unit,
                    $resolution,
                    array_merge([[$metric]], $dimensions ?? []),
                    $properties
                ),
                'json'
            );

            $this->metricsLogger->info($embeddedMetric);
        } catch (ExceptionInterface $e) {
            $this->metricsLogger->error(
                'Failed to serialize metric.',
                [
                    'exception' => $e,
                    'metric' => $metric,
                    'value' => $value,
                    'unit' => $unit,
                    'resolution' => $resolution,
                    'dimensions' => $dimensions,
                    'properties' => $properties,
                ]
            );
        }
    }

    /**
     * Format a singular metric into the Embedded Metric Format.
     *
     * @throws ExceptionInterface
     */
    private function getAsEmbeddedMetric(
        string $namespace,
        string $metric,
        int|float|array $value,
        int $timestamp,
        Unit $unit,
        Resolution $resolution,
        array $dimensions,
        array $properties
    ): array {
        return array_merge(
            $this->getPropertiesWithDefaults(
                [
                    // The metric name and value us always a property we'll want to put
                    // next to the root node
                    $metric => $value,
                ],
                $properties
            ),
            (array)$this->serializer->normalize(
                new RootNode(
                    new Metadata(
                        $timestamp,
                        [
                            new MetricDirective(
                                $namespace,
                                [
                                    new MetricDefinition($metric, $unit, $resolution)
                                ],
                                $dimensions
                            )
                        ]
                    )
                ),
            )
        );
    }

    private function getPropertiesWithDefaults(array ...$customProperties): array
    {
        return array_merge(
            [
                self::FUNCTION_VERSION => getenv('AWS_LAMBDA_FUNCTION_VERSION'),
                self::FUNCTION_NAME => getenv('AWS_LAMBDA_FUNCTION_NAME'),
            ],
            ...$customProperties
        );
    }
}
