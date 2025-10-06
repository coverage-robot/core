<?php

declare(strict_types=1);

namespace Packages\Configuration\Model;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * @internal
 *
 * @see TagBehaviourService
 */
final readonly class IndividualTagBehaviour
{
    public function __construct(
        #[Assert\NotBlank]
        private string $name,
        private bool $carryforward
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCarryforward(): bool
    {
        return $this->carryforward;
    }
}
