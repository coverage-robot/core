<?php

namespace Packages\Models\Model\PublishableMessage;

use DateTimeImmutable;
use DateTimeInterface;
use Packages\Event\Model\EventInterface;
use Packages\Models\Enum\PublishableMessage;

class PublishablePullRequestMessage implements PublishableMessageInterface
{
    public function __construct(
        private readonly EventInterface $event,
        private readonly float $coveragePercentage,
        private readonly float $diffCoveragePercentage,
        private readonly int $successfulUploads,
        private readonly int $pendingUploads,
        private readonly array $tagCoverage,
        private readonly array $leastCoveredDiffFiles,
        private readonly DateTimeImmutable $validUntil,
    ) {
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

    public function getDiffCoveragePercentage(): float
    {
        return $this->diffCoveragePercentage;
    }

    public function getSuccessfulUploads(): int
    {
        return $this->successfulUploads;
    }

    public function getPendingUploads(): int
    {
        return $this->pendingUploads;
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
        return PublishableMessage::PullRequest;
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
