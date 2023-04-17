<?php

namespace App\Model;

use DateTimeImmutable;
use Exception;
use JsonSerializable;

class ProjectCoverage implements JsonSerializable
{
    private DateTimeImmutable $generatedAt;

    /**
     * @var FileCoverage[]
     */
    private array $files = [];

    /**
     * @param int|DateTimeImmutable $generatedAt
     * @throws Exception
     */
    public function __construct(
        int|DateTimeImmutable|null $generatedAt = null
    ) {
        if ($generatedAt !== null) {
            $this->setGeneratedAt($generatedAt);
        }
    }

    public function getGeneratedAt(): DateTimeImmutable
    {
        return $this->generatedAt;
    }

    /**
     * @throws Exception
     */
    public function setGeneratedAt(int|DateTimeImmutable $generatedAt): void
    {
        if (gettype($generatedAt) === 'integer') {
            $this->generatedAt = new DateTimeImmutable();
            $this->generatedAt = $this->generatedAt->setTimestamp($generatedAt);
            return;
        }

        $this->generatedAt = $generatedAt;
    }

    /**
     * @return FileCoverage[]
     */
    public function getFileCoverage(): array
    {
        return $this->files;
    }

    public function addFileCoverage(FileCoverage $file): void
    {
        $this->files[] = $file;
    }

    public function jsonSerialize(): array
    {
        return [
            'generatedAt' => $this->getGeneratedAt(),
            'files' => $this->getFileCoverage()
        ];
    }
}
