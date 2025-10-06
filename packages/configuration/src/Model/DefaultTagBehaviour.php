<?php

declare(strict_types=1);

namespace Packages\Configuration\Model;


/**
 * @internal
 *
 * @see TagBehaviourService
 */
final readonly class DefaultTagBehaviour
{
    public function __construct(
        private bool $carryforward
    ) {
    }

    public function getCarryforward(): bool
    {
        return $this->carryforward;
    }
}
