<?php

namespace Packages\Telemetry\Model\Metric;

use Symfony\Component\Serializer\Annotation\SerializedName;

final class Dimensions
{
    /**
     * @param string[] $dimension
     */
    public function __construct(
        #[SerializedName('')]
        private readonly array $dimension,
    ) {
    }

    public function getDimension(): array
    {
        return $this->dimension;
    }
}
