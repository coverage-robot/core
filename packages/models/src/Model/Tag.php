<?php

namespace Packages\Models\Model;

use JsonSerializable;
use Stringable;

class Tag implements JsonSerializable, Stringable
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
            (string)($data['tag'] ?? $data['name']),
            (string)$data['commit']
        );
    }

    public function __toString(): string
    {
        return sprintf("Tag#%s-%s", $this->name, $this->commit);
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'commit' => $this->commit,
        ];
    }
}
