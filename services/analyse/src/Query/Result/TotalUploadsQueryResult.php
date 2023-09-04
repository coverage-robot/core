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

    /**
     * @param string[] $successfulUploads
     * @param string[] $pendingUploads
     * @return self
     */
    public static function from(array $successfulUploads, array $pendingUploads): self
    {
        return new self($successfulUploads, $pendingUploads);
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
