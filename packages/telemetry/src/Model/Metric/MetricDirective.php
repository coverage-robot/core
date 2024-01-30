<?php

namespace Packages\Telemetry\Model\Metric;

use Symfony\Component\Serializer\Annotation\SerializedName;

final class MetricDirective
{
    /**
     * @param string[][]|null $dimensions
     * @param MetricDefinition[] $metrics
     */
    public function __construct(
        #[SerializedName('Namespace')]
        private readonly string $namespace,
        #[SerializedName('Metrics')]
        private readonly array $metrics,
        #[SerializedName('Dimensions')]
        private readonly array $dimensions,
    ) {
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getMetrics(): array
    {
        return $this->metrics;
    }

    public function getDimensions(): array
    {
        return $this->dimensions;
    }
}
