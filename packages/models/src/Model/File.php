<?php

namespace Packages\Models\Model;

use JsonSerializable;
use OutOfBoundsException;
use Packages\Models\Model\Line\AbstractLine;

class File implements JsonSerializable
{
    /**
     * @param string $fileName
     * @param array<array-key, AbstractLine> $lines
     */
    public function __construct(
        private readonly string $fileName,
        private array $lines = []
    ) {
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getAllLines(): array
    {
        return array_values($this->lines);
    }

    public function getLine(string $lineIdentifier): AbstractLine
    {
        if (!array_key_exists($lineIdentifier, $this->lines)) {
            throw new OutOfBoundsException(
                sprintf('No coverage recorded for line: %s', $lineIdentifier)
            );
        }

        return $this->lines[$lineIdentifier];
    }

    public function setLine(AbstractLine $line): void
    {
        $this->lines[$line->getUniqueLineIdentifier()] = $line;
    }

    public function toString(): string
    {
        return 'File#' . $this->getFileName();
    }

    public function jsonSerialize(): array
    {
        return [
            'fileName' => $this->getFileName(),
            'lines' => $this->getAllLines()
        ];
    }
}
