<?php

namespace Packages\Message\PublishableMessage;

use DateTimeImmutable;
use Override;
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
        private readonly ?int $uncoveredLinesChange = 0,
        #[Assert\GreaterThanOrEqual(-100)]
        #[Assert\LessThanOrEqual(100)]
        private readonly ?float $coverageChange = 0,
        private ?DateTimeImmutable $validUntil = null,
    ) {
        if (!$this->validUntil instanceof DateTimeImmutable) {
            $this->validUntil = new DateTimeImmutable();
        }
    }

    #[Override]
    public function getEvent(): EventInterface
    {
        return $this->event;
    }

    #[Override]
    public function getValidUntil(): DateTimeImmutable
    {
        return $this->validUntil;
    }

    #[Override]
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

    public function getUncoveredLinesChange(): ?int
    {
        return $this->uncoveredLinesChange;
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

    #[Override]
    public function getType(): PublishableMessage
    {
        return PublishableMessage::PULL_REQUEST;
    }

    #[Override]
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
