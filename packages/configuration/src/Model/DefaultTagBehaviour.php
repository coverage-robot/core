<?php

declare(strict_types=1);

namespace Packages\Configuration\Model;


/**
 * @internal
 *
 * @see TagBehaviourService
 */
final class DefaultTagBehaviour
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
