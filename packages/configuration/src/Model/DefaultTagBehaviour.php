<?php

namespace Packages\Configuration\Model;

use Symfony\Component\Serializer\Annotation\SerializedName;

class DefaultTagBehaviour
{
    public function __construct(
        private readonly bool $carryforward
    ) {
    }

    #[SerializedName('carryforward')]
    public function shouldCarryforward(): bool
    {
        return $this->carryforward;
    }
}
