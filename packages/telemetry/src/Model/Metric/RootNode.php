<?php

declare(strict_types=1);

namespace Packages\Telemetry\Model\Metric;

use Symfony\Component\Serializer\Annotation\SerializedName;

final class RootNode
{
    public function __construct(
        #[SerializedName('_aws')]
        private readonly Metadata $metadata
    ) {
    }

    public function getMetadata(): Metadata
    {
        return $this->metadata;
    }
}
