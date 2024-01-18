<?php

namespace App\Model;

use App\Model\Line\AbstractLine;
use Countable;
use OutOfBoundsException;
use Override;
use Stringable;

class File implements Countable, Stringable
{
    private int $lineCount;

    /**
     * @param array<array-key, AbstractLine> $lines
     */
    public function __construct(
        private readonly string $fileName,
        private array $lines = []
    ) {
        $this->lineCount = count($lines);
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    /**
     * @return AbstractLine[]
     */
    public function getLines(): array
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
        if (!array_key_exists($line->getUniqueLineIdentifier(), $this->lines)) {
            ++$this->lineCount;
        }

        $this->lines[$line->getUniqueLineIdentifier()] = $line;
    }

    #[Override]
    public function __toString(): string
    {
        return 'File#' . $this->getFileName();
    }

    #[Override]
    public function count(): int
    {
        return $this->lineCount;
    }
}
