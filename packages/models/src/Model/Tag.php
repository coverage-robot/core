<?php

namespace Packages\Models\Model;

class Tag
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


    public static function from(array $data): self
    {
        return new self(
            (string)$data['tag'],
            (string)$data['commit']
        );
    }

    public function __toString(): string
    {
        return sprintf("Tag#%s-%s", $this->name, $this->commit);
    }
}
