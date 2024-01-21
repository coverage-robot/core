<?php

namespace Packages\Contracts\Event;

use DateTimeImmutable;
use Packages\Contracts\Provider\Provider;
use Stringable;
use Symfony\Component\Validator\Constraints as Assert;

interface EventInterface extends Stringable
{
    public function getProvider(): Provider;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[\\w\-\.]+$/i')]
    public function getOwner(): string;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[\\w\-\.]+$/i')]
    public function getRepository(): string;

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
