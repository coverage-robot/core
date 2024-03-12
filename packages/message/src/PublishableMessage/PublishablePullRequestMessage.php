<?php

namespace Packages\Message\PublishableMessage;

use DateTimeImmutable;
use Packages\Contracts\PublishableMessage\PublishableMessage;
use Packages\Event\Model\EventInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class PublishablePullRequestMessage implements PublishableMessageInterface
{
    public function __construct(
        private readonly EventInterface $event,
        #[Assert\PositiveOrZero]
        #[Assert\LessThanOrEqual(100)]
        private readonly float $coveragePercentage,
        #[Assert\PositiveOrZero]
        #[Assert\LessThanOrEqual(100)]
        private readonly ?float $diffCoveragePercentage,
        #[Assert\PositiveOrZero]
        private readonly ?float $diffUncoveredLines,
        #[Assert\PositiveOrZero]
        private readonly int $successfulUploads,
        private readonly array $tagCoverage,
        private readonly array $leastCoveredDiffFiles,
        #[Assert\NotBlank(allowNull: true)]
        private readonly ?string $baseCommit = null,
        #[Assert\GreaterThanOrEqual(-100)]
        #[Assert\LessThanOrEqual(100)]
        private readonly ?float $coverageChange = 0,
        private ?DateTimeImmutable $validUntil = null,
    ) {
        if (!$this->validUntil instanceof DateTimeImmutable) {
            $this->validUntil = new DateTimeImmutable();
        }
    }

    public function getEvent(): EventInterface
    {
        return $this->event;
    }

    public function getValidUntil(): DateTimeImmutable
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

    public function getDiffUncoveredLines(): ?float
    {
        return $this->diffUncoveredLines;
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
