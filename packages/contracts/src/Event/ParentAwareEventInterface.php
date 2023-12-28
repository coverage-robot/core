<?php

namespace Packages\Contracts\Event;

use Stringable;

interface ParentAwareEventInterface extends Stringable
{
    /**
     * @return string[]
     */
    public function getParent(): array;
}
