<?php

declare(strict_types=1);

namespace App\Query\Result;

use DateTimeImmutable;
use Packages\Contracts\Tag\Tag;
use Symfony\Component\Validator\Constraints as Assert;

final class TotalUploadsQueryResult implements QueryResultInterface
{
    /**
     * @param string[] $successfulUploads
     * @param DateTimeImmutable[] $successfulIngestTimes
     * @param Tag[] $successfulTags
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
        private readonly array $successfulTags
    ) {
    }

    /**
     * @return string[]
     */
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

    /**
     * @return DateTimeImmutable[]
     */
    public function getSuccessfulIngestTimes(): array
    {
        return $this->successfulIngestTimes;
    }
}
