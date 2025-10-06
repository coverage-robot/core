<?php

declare(strict_types=1);

namespace Packages\Telemetry\Model\Metric;

use Symfony\Component\Serializer\Annotation\SerializedName;

final readonly class Dimensions
{
    /**
     * @param string[] $dimension
     */
    public function __construct(
        #[SerializedName('')]
        private array $dimension,
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
