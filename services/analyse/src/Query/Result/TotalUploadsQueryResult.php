<?php

namespace App\Query\Result;

use DateTimeImmutable;
use Packages\Models\Model\Tag;

class TotalUploadsQueryResult implements QueryResultInterface
{
    /**
     * @param string[] $successfulUploads
     * @param Tag[] $successfulTags
     * @param DateTimeImmutable|null $latestSuccessfulUpload
     */
    public function __construct(
        private readonly array $successfulUploads,
        private readonly array $successfulTags,
        private readonly ?DateTimeImmutable $latestSuccessfulUpload
    ) {
    }

    public function getSuccessfulUploads(): array
    {
        return $this->successfulUploads;
    }

    /**
     * @return Tag[]
     */
    public function getSuccessfulTags(): array
    {
        return $this->successfulTags;
    }

    public function getLatestSuccessfulUpload(): DateTimeImmutable|null
    {
        return $this->latestSuccessfulUpload;
    }
}
