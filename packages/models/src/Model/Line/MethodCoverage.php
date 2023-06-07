<?php

namespace Packages\Models\Model\Line;

use Packages\Models\Enum\LineType;

class MethodCoverage extends AbstractLineCoverage
{
    public function __construct(
        int $lineNumber,
        int $lineHits,
        private readonly string $name,
    ) {
        parent::__construct($lineNumber, $lineHits);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getUniqueLineIdentifier(): string
    {
        return $this->getName();
    }

    public function getType(): LineType
    {
        return LineType::METHOD;
    }

    public function jsonSerialize(): array
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'name' => $this->getName(),
            ]
        );
    }
}
