<?php

namespace App\Query\Result;

class TotalUploadsQueryResult implements QueryResultInterface
{
    /**
     * @param string[] $successfulUploads
     * @param string[] $pendingUploads
     */
    private function __construct(
        private readonly array $successfulUploads,
        private readonly array $pendingUploads
    ) {
    }

    public static function from(array $successfulUploads, array $pendingUploads): self
    {
        return new self(
            array_filter($successfulUploads, static fn(mixed $uploadId) => is_string($uploadId)),
            array_filter($pendingUploads, static fn(mixed $uploadId) => is_string($uploadId))
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
}
