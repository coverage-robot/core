<?php

namespace Packages\Telemetry\Service;

use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Telemetry\Enum\EnvironmentVariable;
use Packages\Telemetry\Enum\Resolution;
use Packages\Telemetry\Enum\Unit;
use Packages\Telemetry\Model\Metric\Metadata;
use Packages\Telemetry\Model\Metric\MetricDefinition;
use Packages\Telemetry\Model\Metric\MetricDirective;
use Packages\Telemetry\Model\Metric\RootNode;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class MetricService implements MetricServiceInterface
{
    private const NAMESPACE = 'Metrics';

    public const FUNCTION_VERSION = 'functionVersion';

    public const FUNCTION_NAME = 'functionName';

    public function __construct(
        private readonly LoggerInterface $metricsLogger,
        #[Autowire(service: NativeClock::class)]
        private readonly ClockInterface $clock,
        private readonly EnvironmentServiceInterface $environmentService,
        private readonly SerializerInterface&NormalizerInterface $serializer
    ) {
    }

    /**
     * @inheritDoc
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
                        ->format("Uv"),
                    $unit,
                    $resolution,
                    array_merge([[self::FUNCTION_NAME]], $dimensions ?? []),
                    $properties
                ),
                'json'
            );

            $this->metricsLogger->info($embeddedMetric);
        } catch (ExceptionInterface $exception) {
            $this->metricsLogger->error(
                'Failed to serialize metric.',
                [
                    'exception' => $exception,
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
                self::FUNCTION_VERSION => $this->environmentService->getVariable(
                    EnvironmentVariable::AWS_LAMBDA_FUNCTION_VERSION
                ),
                self::FUNCTION_NAME => $this->environmentService->getVariable(
                    EnvironmentVariable::AWS_LAMBDA_FUNCTION_NAME
                ),
            ],
            ...$customProperties
        );
    }
}
