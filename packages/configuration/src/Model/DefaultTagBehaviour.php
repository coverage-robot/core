<?php

namespace Packages\Configuration\Model;

class DefaultTagBehaviour
{
    public function __construct(
        private readonly bool $carryforward
    ) {
    }

    public function getCarryforward(): bool
    {
        return $this->carryforward;
    }
}
