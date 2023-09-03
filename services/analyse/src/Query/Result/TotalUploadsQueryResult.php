<?php

namespace App\Query\Result;

class TotalUploadsQueryResult implements QueryResultInterface
{
    private function __construct(
        private readonly int $successfulUploads,
        private readonly int $pendingUploads
    ) {
    }

    public static function from(int $successfulUploads, int $pendingUploads): self
    {
        return new self($successfulUploads, $pendingUploads);
    }

    public function getSuccessfulUploads(): int
    {
        return $this->successfulUploads;
    }

    public function getPendingUploads(): int
    {
        return $this->pendingUploads;
    }
}
