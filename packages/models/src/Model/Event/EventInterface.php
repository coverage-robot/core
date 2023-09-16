<?php

namespace Packages\Models\Model\Event;

use DateTimeImmutable;
use JsonSerializable;
use Packages\Models\Enum\Provider;
use Stringable;

interface EventInterface extends JsonSerializable, Stringable
{
    public function getProvider(): Provider;

    public function getOwner(): string;

    public function getRepository(): string;

    public function getCommit(): string;

    public function getPullRequest(): int|string|null;

    public function getRef(): string;

    public function getEventTime(): DateTimeImmutable;

    public static function from(array $data): self;
}
