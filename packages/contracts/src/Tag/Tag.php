<?php

namespace Packages\Contracts\Tag;

use Stringable;
use Symfony\Component\Validator\Constraints as Assert;

class Tag implements Stringable
{
    public function __construct(
        #[Assert\NotBlank]
        private readonly string $name,
        #[Assert\NotBlank]
        private readonly string $commit
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCommit(): string
    {
        return $this->commit;
    }

    public function __toString(): string
    {
        return sprintf("Tag#%s-%s", $this->name, $this->commit);
    }
}
