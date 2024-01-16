<?php

namespace App\Model;

use Packages\Contracts\Provider\Provider;
use Symfony\Component\Validator\Constraints as Assert;

interface ParametersInterface
{
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[\\w\-\.]+$/i')]
    public function getRepository(): string;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[\\w\-\.]+$/i')]
    public function getOwner(): string;

    public function getProvider(): Provider;
}
