<?php

namespace App\Query\Result;

use DateTimeImmutable;
use Packages\Contracts\Tag\Tag;
use Symfony\Component\Validator\Constraints as Assert;

class TotalUploadsQueryResult implements QueryResultInterface
{
    /**
     * @param string[] $successfulUploads
     * @param DateTimeImmutable[] $successfulIngestTimes
     * @param Tag[] $successfulTags
     * @param DateTimeImmutable|null $latestSuccessfulUpload
     */
    public function __construct(
        #[Assert\All([
            new Assert\Type(type: 'string'),
            new Assert\NotBlank()
        ])]
        private readonly array $successfulUploads,
        #[Assert\All([
            new Assert\Type(type: DateTimeImmutable::class),
            new Assert\LessThanOrEqual(value: 'now')
        ])]
        private readonly array $successfulIngestTimes,
        #[Assert\All([
            new Assert\Type(type: Tag::class)
        ])]
        private readonly array $successfulTags,
        #[Assert\LessThanOrEqual(value: 'now')]
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

    public function getSuccessfulIngestTimes(): array
    {
        return $this->successfulIngestTimes;
    }
}
