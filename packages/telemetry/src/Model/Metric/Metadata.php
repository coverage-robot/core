<?php

declare(strict_types=1);

namespace Packages\Telemetry\Model\Metric;

use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class Metadata
{
    /**
     * @param MetricDirective[] $directives
     */
    public function __construct(
        #[SerializedName('Timestamp')]
        private int $timestamp,
        #[SerializedName('CloudWatchMetrics')]
        private array $directives,
    ) {
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * @return MetricDirective[]
     */
    public function getDirectives(): array
    {
        return $this->directives;
    }
}
