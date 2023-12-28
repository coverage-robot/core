<?php

namespace Packages\Contracts\Event;

use Stringable;

interface BaseAwareEventInterface extends Stringable
{
    public function getBaseRef(): ?string;

    public function getBaseCommit(): ?string;
}
