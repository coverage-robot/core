<?php

namespace Packages\Models\Model;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use JsonSerializable;
use Packages\Models\Enum\CoverageFormat;

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
        private readonly CoverageFormat $sourceFormat,
        private readonly string $root,
        int|DateTimeImmutable|null $generatedAt = null
    ) {
        if ($generatedAt !== null) {
            $this->setGeneratedAt($generatedAt);
        }
    }

    public function getSourceFormat(): CoverageFormat
    {
        return $this->sourceFormat;
    }

    public function getRoot(): string
    {
        return $this->root;
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
            $currentTimestamp = (new DateTimeImmutable())->getTimestamp();
            if ($generatedAt > $currentTimestamp) {
                // The timestamp MUST be in microseconds (since the timestamp is larger than
                // the current timestamp, which is in seconds)
                $generatedAt = $generatedAt / 1000;
            }

            // Format the generated date using Epoch time (in seconds), making sure to clamp the
            // timestamp, so it doesn't exceed the current time (coverage can't have been generated
            // in the future!)
            $this->generatedAt = DateTimeImmutable::createFromFormat(
                'U.u',
                number_format(
                    min($currentTimestamp, $generatedAt),
                    3,
                    '.',
                    ''
                )
            );
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

    public function toString(): string
    {
        return 'Project#' . ($this->getGeneratedAt()?->format(DateTimeInterface::ATOM) ?? 'null');
    }

    public function jsonSerialize(): array
    {
        return [
            'sourceFormat' => $this->sourceFormat,
            'root' => $this->root,
            'generatedAt' => $this->getGeneratedAt()?->format(DateTimeInterface::ATOM),
            'files' => $this->getFiles()
        ];
    }
}
