<?php

namespace Packages\Message\PublishableMessage;

use DateTimeImmutable;
use DateTimeInterface;
use Packages\Contracts\PublishableMessage\PublishableMessage;
use Packages\Event\Model\EventInterface;

class PublishablePullRequestMessage implements PublishableMessageInterface
{
    public function __construct(
        private readonly EventInterface $event,
        private readonly float $coveragePercentage,
        private readonly ?float $diffCoveragePercentage,
        private readonly int $successfulUploads,
        private readonly array $tagCoverage,
        private readonly array $leastCoveredDiffFiles,
        private readonly ?string $baseCommit = null,
        private readonly ?float $coverageChange = 0,
        private ?DateTimeImmutable $validUntil = null,
    ) {
        if ($this->validUntil === null) {
            $this->validUntil = new DateTimeImmutable();
        }
    }

    public function getEvent(): EventInterface
    {
        return $this->event;
    }

    public function getValidUntil(): DateTimeInterface
    {
        return $this->validUntil;
    }

    public function getMessageGroup(): string
    {
        return md5(
            implode('', [
                $this->event->getProvider()->value,
                $this->event->getOwner(),
                $this->event->getRepository(),
                $this->event->getPullRequest()
            ])
        );
    }

    public function getCoveragePercentage(): float
    {
        return $this->coveragePercentage;
    }

    public function getBaseCommit(): ?string
    {
        return $this->baseCommit;
    }

    public function getCoverageChange(): ?float
    {
        return $this->coverageChange;
    }

    public function getDiffCoveragePercentage(): float|null
    {
        return $this->diffCoveragePercentage;
    }

    public function getSuccessfulUploads(): int
    {
        return $this->successfulUploads;
    }

    public function getTagCoverage(): array
    {
        return $this->tagCoverage;
    }

    public function getLeastCoveredDiffFiles(): array
    {
        return $this->leastCoveredDiffFiles;
    }

    public function getType(): PublishableMessage
    {
        return PublishableMessage::PULL_REQUEST;
    }

    public function __toString(): string
    {
        return sprintf(
            "PublishablePullRequestMessage#%s-%s-%s",
            $this->event->getOwner(),
            $this->event->getRepository(),
            $this->event->getCommit()
        );
    }
}
