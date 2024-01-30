<?php

namespace Packages\Configuration\Model;

use Symfony\Component\Validator\Constraints as Assert;

final class IndividualTagBehaviour
{
    public function __construct(
        #[Assert\NotBlank]
        private readonly string $name,
        private readonly bool $carryforward
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
