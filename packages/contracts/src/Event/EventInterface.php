<?php

declare(strict_types=1);

namespace Packages\Contracts\Event;

use DateTimeImmutable;
use Stringable;
use Symfony\Component\Validator\Constraints as Assert;

interface EventInterface extends Stringable, ProjectAwareEventInterface
{
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-f0-9]{40}$/')]
    public function getCommit(): string;

    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Regex(pattern: '/^\d+$/')]
    public function getPullRequest(): int|string|null;

    #[Assert\NotBlank]
    public function getRef(): string;

    public function getType(): Event;

    #[Assert\LessThanOrEqual('now')]
    public function getEventTime(): DateTimeImmutable;
}
