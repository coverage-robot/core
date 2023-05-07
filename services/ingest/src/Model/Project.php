<?php

namespace App\Model;

use App\Enum\CoverageFormatEnum;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use JsonSerializable;

class Project implements JsonSerializable
{
    private ?DateTimeImmutable $generatedAt = null;

    /**
     * @var File[]
     */
    private array $files = [];

    /**
     * @param int|DateTimeImmutable $generatedAt
     * @throws Exception
     */
    public function __construct(
        private readonly CoverageFormatEnum $sourceFormat,
        int|DateTimeImmutable|null $generatedAt = null
    ) {
        if ($generatedAt !== null) {
            $this->setGeneratedAt($generatedAt);
        }
    }

    public function getSourceFormat(): CoverageFormatEnum
    {
        return $this->sourceFormat;
    }

    public function getGeneratedAt(): ?DateTimeImmutable
    {
        return $this->generatedAt;
    }

    /**
     * @throws Exception
     */
    public function setGeneratedAt(int|DateTimeImmutable|null $generatedAt): void
    {
        if (gettype($generatedAt) === 'integer') {
            $this->generatedAt = new DateTimeImmutable();
            $this->generatedAt = $this->generatedAt->setTimestamp($generatedAt);
            return;
        }

        $this->generatedAt = $generatedAt;
    }

    /**
     * @return File[]
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    public function addFile(File $file): void
    {
        $this->files[] = $file;
    }

    public function jsonSerialize(): array
    {
        return [
            'sourceFormat' => $this->sourceFormat,
            'generatedAt' => $this->getGeneratedAt()?->format(DateTimeInterface::ATOM),
            'files' => $this->getFiles()
        ];
    }
}
