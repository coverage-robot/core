<?php

namespace App\Model;

use DateTimeImmutable;
use Override;
use Packages\Contracts\Tag\Tag;
use Symfony\Component\Validator\Constraints as Assert;

final class CarryforwardTag extends Tag
{
    /**
     * @param int[] $successfullyUploadedLines
     * @param DateTimeImmutable[] $ingestTimes
     */
    public function __construct(
        string $name,
        string $commit,
        array $successfullyUploadedLines,
        #[Assert\All([
            new Assert\Type(type: DateTimeImmutable::class),
            new Assert\LessThanOrEqual('now')
        ])]
        private readonly array $ingestTimes,
    ) {
        parent::__construct($name, $commit, $successfullyUploadedLines);
    }

    public function getIngestTimes(): array
    {
        return $this->ingestTimes;
    }

    #[Override]
    public function __toString(): string
    {
        return sprintf('CarryforwardTag#%s-%s', $this->getName(), $this->getCommit());
    }
}
