<?php

namespace Packages\Telemetry\Model\Metric;

use Symfony\Component\Serializer\Annotation\SerializedName;

class RootNode
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
