<?php

namespace Packages\Contracts\Event;

use Packages\Contracts\Provider\Provider;
use Symfony\Component\Validator\Constraints as Assert;

interface ProjectAwareEventInterface
{
    public function getProvider(): Provider;

    #[Assert\NotBlank]
    #[Assert\Uuid(versions: [Assert\Uuid::V7_MONOTONIC])]
    public function getProjectId(): string;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[\\w\-\.]+$/i')]
    public function getOwner(): string;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[\\w\-\.]+$/i')]
    public function getRepository(): string;
}