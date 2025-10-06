<?php

declare(strict_types=1);

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

    /**
     * @return string[]
     */
    public function getDimension(): array
    {
        return $this->dimension;
    }
}
