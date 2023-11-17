<?php

namespace Packages\Contracts\Event;

use DateTimeImmutable;
use Packages\Contracts\Provider\Provider;
use Stringable;

interface EventInterface extends Stringable
{
    public function getProvider(): Provider;

    public function getOwner(): string;

    public function getRepository(): string;

    public function getCommit(): string;

    public function getPullRequest(): int|string|null;

    public function getRef(): string;

    public function getType(): Event;

    public function getEventTime(): DateTimeImmutable;
}
