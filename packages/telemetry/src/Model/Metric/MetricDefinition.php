<?php

declare(strict_types=1);

namespace Packages\Telemetry\Model\Metric;

use Packages\Telemetry\Enum\Resolution;
use Packages\Telemetry\Enum\Unit;
use Symfony\Component\Serializer\Annotation\SerializedName;

final readonly class MetricDefinition
{
    public function __construct(
        #[SerializedName('Name')]
        private string $name,
        #[SerializedName('Unit')]
        private Unit $unit,
        #[SerializedName('StorageResolution')]
        private Resolution $resolution,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getUnit(): Unit
    {
        return $this->unit;
    }

    public function getResolution(): Resolution
    {
        return $this->resolution;
    }
}
