<?php

declare(strict_types=1);

namespace Packages\Telemetry\Model\Metric;

use Symfony\Component\Serializer\Annotation\SerializedName;

final readonly class MetricDirective
{
    /**
     * @param string[][] $dimensions
     * @param MetricDefinition[] $metrics
     */
    public function __construct(
        #[SerializedName('Namespace')]
        private string $namespace,
        #[SerializedName('Metrics')]
        private array $metrics,
        #[SerializedName('Dimensions')]
        private array $dimensions,
    ) {
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * @return MetricDefinition[]
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    /**
     * @return string[][]
     */
    public function getDimensions(): array
    {
        return $this->dimensions;
    }
}
