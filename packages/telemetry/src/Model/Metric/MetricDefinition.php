<?php

namespace Packages\Telemetry\Model\Metric;

use Packages\Telemetry\Enum\Resolution;
use Packages\Telemetry\Enum\Unit;
use Symfony\Component\Serializer\Annotation\SerializedName;

final class MetricDefinition
{
    public function __construct(
        #[SerializedName('Name')]
        private readonly string $name,
        #[SerializedName('Unit')]
        private readonly Unit $unit,
        #[SerializedName('StorageResolution')]
        private readonly Resolution $resolution,
    )
    {
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