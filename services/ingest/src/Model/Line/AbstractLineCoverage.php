<?php

namespace App\Model\Line;

use App\Enum\LineTypeEnum;
use JsonSerializable;

abstract class AbstractLineCoverage implements JsonSerializable
{
    public function __construct(
        private readonly int $lineNumber,
        private readonly int $lineHits = 0
    ) {
    }

    abstract public function getType(): LineTypeEnum;

    /**
     * Build a unique line identifier, which can be used for indexing a lookups.
     *
     * Generally this will be the line number, however there are specific times we may
     * need to uniquely identify a line slightly differently.
     *
     * For example, when tracking coverage for a method, the line number does not dictate
     * that a second method may not also reside on this line. In this particular case,
     * we need to use the method name.
     *
     * @return string
     *
     * @see MethodCoverage::getUniqueLineIdentifier()
     */
    public function getUniqueLineIdentifier(): string
    {
        return (string)$this->getLineNumber();
    }

    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }

    public function getLineHits(): int
    {
        return $this->lineHits;
    }

    public function __toString(): string
    {
        return sprintf(
            '%s#%s',
            ucfirst(strtolower($this->getType()->name)),
            $this->getUniqueLineIdentifier()
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->getType()->name,
            'lineNumber' => $this->getLineNumber(),
            'lineHits' => $this->getLineHits()
        ];
    }
}
