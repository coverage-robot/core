<?php

namespace App\Model\Line;

use App\Enum\LineTypeEnum;

class MethodCoverage extends AbstractLineCoverage
{
    public function __construct(
        int $lineNumber,
        int $lineHits = 0,
        private readonly ?string $name = null,
    ) {
        parent::__construct($lineNumber, $lineHits);
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getUniqueLineIdentifier(): string
    {
        return $this->getName();
    }

    public function getType(): LineTypeEnum
    {
        return LineTypeEnum::METHOD;
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
