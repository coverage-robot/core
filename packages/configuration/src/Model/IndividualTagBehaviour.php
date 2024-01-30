<?php

namespace Packages\Configuration\Model;

use Symfony\Component\Validator\Constraints as Assert;

final class IndividualTagBehaviour extends DefaultTagBehaviour
{
    public function __construct(
        #[Assert\NotBlank]
        private readonly string $name,
        bool $carryforward
    ) {
        parent::__construct($carryforward);
    }

    public function getName(): string
    {
        return $this->name;
    }
}
