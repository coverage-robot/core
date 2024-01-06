<?php

namespace Packages\Configuration\Model;

use Symfony\Component\Validator\Constraints as Assert;

class PathReplacement
{
    public function __construct(
        #[Assert\NotBlank]
        private string $before,
        private string $after,
    ) {
    }

    public function getBefore(): string
    {
        return $this->before;
    }

    public function getAfter(): string
    {
        return $this->after;
    }
}
