<?php

namespace App\Query\Result;

use DateTimeImmutable;
use Packages\Contracts\Tag\Tag;
use Symfony\Component\Validator\Constraints as Assert;

class AvailableTagQueryResult extends Tag
{
    /**
     * @param DateTimeImmutable[] $ingestTimes
     */
    public function __construct(
        #[Assert\NotBlank]
        string $name,
        #[Assert\NotBlank]
        string $commit,
        #[Assert\All([
            new Assert\Type(type: DateTimeImmutable::class),
            new Assert\LessThanOrEqual('now')
        ])]
        private array $ingestTimes
    ) {
        parent::__construct($name, $commit);
    }

    public function getIngestTimes(): array
    {
        return $this->ingestTimes;
    }
}
