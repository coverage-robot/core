<?php

namespace Packages\Telemetry\Model\Metric;

use Symfony\Component\Serializer\Annotation\SerializedName;

final class Metadata
{
    /**
     * @param MetricDirective[] $directives
     */
    public function __construct(
        #[SerializedName('Timestamp')]
        private readonly int $timestamp,
        #[SerializedName('CloudWatchMetrics')]
        private readonly array $directives,
    ) {
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    public function getDirectives(): array
    {
        return $this->directives;
    }
}
