<?php

namespace Packages\Contracts\Event;

use Stringable;
use Symfony\Component\Validator\Constraints as Assert;

interface ParentAwareEventInterface extends Stringable
{
    /**
     * @return string[]
     */
    #[Assert\All([
        new Assert\Type('string')
    ])]
    public function getParent(): array;
}
