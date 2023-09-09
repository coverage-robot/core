<?php

namespace App\Query\Result;

use DateTimeImmutable;
use DateTimeInterface;

class TotalUploadsQueryResult implements QueryResultInterface
{
    /**
     * @param string[] $successfulUploads
     * @param string[] $pendingUploads
     */
    private function __construct(
        private readonly array $successfulUploads,
        private readonly array $pendingUploads,
        private readonly ?DateTimeImmutable $latestSuccessfulUpload
    ) {
    }

    public static function from(
        array $successfulUploads,
        array $pendingUploads,
        ?string $latestSuccessfulUpload = null
    ): self {
        return new self(
            array_filter($successfulUploads, static fn(mixed $uploadId) => is_string($uploadId)),
            array_filter($pendingUploads, static fn(mixed $uploadId) => is_string($uploadId)),
            $latestSuccessfulUpload ? (DateTimeImmutable::createFromFormat(
                DateTimeInterface::ATOM,
                $latestSuccessfulUpload
            ) ?: null) : null
        );
    }

    public function getSuccessfulUploads(): array
    {
        return $this->successfulUploads;
    }

    public function getPendingUploads(): array
    {
        return $this->pendingUploads;
    }

    public function getLatestSuccessfulUpload(): DateTimeImmutable|null
    {
        return $this->latestSuccessfulUpload;
    }
}
