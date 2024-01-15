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
        new Assert\Type('string'),
        new Assert\Regex(pattern: '/^[a-f0-9]{40}$/')
    ])]
    public function getParent(): array;
}
