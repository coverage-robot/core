<?php

namespace Packages\Models\Model\Event;

use JsonSerializable;
use Packages\Models\Enum\Provider;
use Stringable;

interface EventInterface extends JsonSerializable, Stringable
{
    public function getProvider(): Provider;

    public function getOwner(): string;

    public function getRepository(): string;

    public static function from(array $data): self;
}
