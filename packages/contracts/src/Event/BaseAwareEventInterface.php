<?php

namespace Packages\Contracts\Event;

use Stringable;
use Symfony\Component\Validator\Constraints as Assert;

interface BaseAwareEventInterface extends Stringable
{
    #[Assert\NotBlank(allowNull: true)]
    public function getBaseRef(): ?string;

    #[Assert\NotBlank(allowNull: true)]
    public function getBaseCommit(): ?string;
}
