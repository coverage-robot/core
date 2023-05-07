<?php

namespace App\Model;

use App\Model\Line\AbstractLineCoverage;
use JsonSerializable;
use OutOfBoundsException;

class File implements JsonSerializable
{
    /**
     * @param string $fileName
     * @param array<array-key, AbstractLineCoverage> $lines
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

    public function getAllLineCoverage(): array
    {
        return array_values($this->lines);
    }

    public function getSpecificLineCoverage(string $lineIdentifier): AbstractLineCoverage
    {
        if (!array_key_exists($lineIdentifier, $this->lines)) {
            throw new OutOfBoundsException(
                sprintf('No coverage recorded for line: %s', $lineIdentifier)
            );
        }

        return $this->lines[$lineIdentifier];
    }

    public function setLineCoverage(AbstractLineCoverage $line): void
    {
        $this->lines[$line->getUniqueLineIdentifier()] = $line;
    }

    public function toString(): string
    {
        return 'File #' . $this->getFileName();
    }

    public function jsonSerialize(): array
    {
        return [
            'fileName' => $this->getFileName(),
            'lines' => $this->getAllLineCoverage()
        ];
    }
}
