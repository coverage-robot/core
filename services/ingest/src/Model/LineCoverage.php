<?php

namespace App\Model;

use App\Enum\LineTypeEnum;
use JsonSerializable;

class LineCoverage implements JsonSerializable
{
    /**
     * @param LineTypeEnum $type
     * @param int $lineNumber
     * @param string|null $name
     * @param int $lineHits
     * @param int $complexity
     * @param int $crapIndex
     */
    public function __construct(
        private readonly LineTypeEnum $type,
        private readonly int $lineNumber,
        private readonly ?string $name = null,
        private readonly int $lineHits = 0,
        private readonly int $complexity = 0,
        private readonly float $crapIndex = 0,
    ) {
    }

    public function getType(): LineTypeEnum
    {
        return $this->type;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }

    public function getLineHits(): int
    {
        return $this->lineHits;
    }

    public function getComplexity(): int
    {
        return $this->complexity;
    }

    public function getCrapIndex(): float
    {
        return $this->crapIndex;
    }


    public function jsonSerialize(): array
    {
        if ($this->getType() === LineTypeEnum::METHOD) {
            return [
                'type' => $this->getType()->name,
                'name' => $this->getName(),
                'lineNumber' => $this->getLineNumber(),
                'count' => $this->getLineHits(),
                'complexity' => $this->getComplexity(),
                'crap' => $this->getCrapIndex()
            ];
        }

        return [
            'type' => $this->getType()->name,
            'lineNumber' => $this->getLineNumber(),
            'count' => $this->getLineHits(),
            'complexity' => $this->getComplexity(),
            'crap' => $this->getCrapIndex()
        ];
    }
}
