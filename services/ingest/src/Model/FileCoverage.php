<?php

namespace App\Model;

class FileCoverage implements \JsonSerializable
{
    /**
     * @var LineCoverage[]
     */
    private array $lines = [];

    /**
     * @param string $fileName
     */
    public function __construct(
        private readonly string $fileName
    )
    {
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getLineCoverage(): array
    {
        return $this->lines;
    }

    public function addLineCoverage(LineCoverage $line): void
    {
        $this->lines[] = $line;
    }

    public function jsonSerialize(): array
    {
        return [
            "fileName" => $this->getFileName(),
            "lines" => $this->getLineCoverage()
        ];
    }
}