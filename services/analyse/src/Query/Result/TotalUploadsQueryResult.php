<?php

namespace App\Query\Result;

use DateTimeImmutable;
use DateTimeInterface;
use Packages\Models\Model\Tag;

class TotalUploadsQueryResult implements QueryResultInterface
{
    /**
     * @param string[] $successfulUploads
     * @param Tag[] $successfulTags
     * @param string[] $pendingUploads
     */
    public function __construct(
        private readonly array $successfulUploads,
        private readonly array $successfulTags,
        private readonly ?DateTimeImmutable $latestSuccessfulUpload
    ) {
    }

    public static function from(
        string $commit,
        array $successfulUploads,
        array $successfulTags,
        ?string $latestSuccessfulUpload = null
    ): self {
        return new self(
            array_filter(
                $successfulUploads,
                static fn(mixed $uploadId) => is_string($uploadId)
            ),
            array_map(
                static fn(string $tag) => new Tag($tag, $commit),
                array_filter($successfulTags, static fn(mixed $tag) => is_string($tag))
            ),
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
