<?php

namespace Packages\Models\Model;

use Stringable;

class Tag implements Stringable
{
    public function __construct(
        private readonly string $name,
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
