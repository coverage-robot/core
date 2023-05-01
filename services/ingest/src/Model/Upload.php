<?php

namespace App\Model;

use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;

class Upload implements JsonSerializable
{
    private readonly DateTimeImmutable $ingestTime;

    /**
     * @param Project $project
     * @param string $uploadId
     * @param string $commit
     * @param string $parent
     * @param DateTimeInterface|null $ingestTime
     */
    public function __construct(
        private readonly Project $project,
        private readonly string $uploadId,
        private readonly string $commit,
        private readonly string $parent,
        ?DateTimeInterface $ingestTime = null
    ) {
        if ($ingestTime) {
            $this->ingestTime = DateTimeImmutable::createFromInterface($ingestTime);
            return;
        }

        $this->ingestTime = new DateTimeImmutable();
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function getUploadId(): string
    {
        return $this->uploadId;
    }

    public function getIngestTime(): DateTimeImmutable
    {
        return $this->ingestTime;
    }

    public function getCommit(): string
    {
        return $this->commit;
    }

    public function getParent(): string
    {
        return $this->parent;
    }

    public function __toString(): string
    {
        return "Upload #" . $this->uploadId;
    }

    public function jsonSerialize(): array
    {
        return [
            'uploadId' => $this->uploadId,
            'ingestTime' => $this->ingestTime,
            'commit' => $this->commit,
            'parent' => $this->parent
        ];
    }
}
