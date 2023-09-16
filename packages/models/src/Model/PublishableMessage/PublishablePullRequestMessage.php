<?php

namespace Packages\Models\Model\PublishableMessage;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Packages\Models\Enum\PublishableMessage;
use Packages\Models\Model\Event\EventInterface;
use Packages\Models\Model\Event\GenericEvent;

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

    public function getType(): PublishableMessage
    {
        return PublishableMessage::PullRequest;
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

    public static function from(array $data): self
    {
        $validUntil = DateTimeImmutable::createFromFormat(
            DateTimeInterface::ATOM,
            $data['validUntil'] ?? ''
        );

        if (
            !isset($data['event']) ||
            !isset($data['coveragePercentage']) ||
            !isset($data['diffCoveragePercentage']) ||
            !isset($data['successfulUploads']) ||
            !isset($data['pendingUploads']) ||
            !isset($data['tagCoverage']) ||
            !isset($data['leastCoveredDiffFiles']) ||
            !$validUntil
        ) {
            throw new InvalidArgumentException("Pull request message is not valid.");
        }

        return new self(
            GenericEvent::from($data['event']),
            (float)$data['coveragePercentage'],
            (float)$data['diffCoveragePercentage'],
            (int)$data['successfulUploads'],
            (int)$data['pendingUploads'],
            (array)$data['tagCoverage'],
            (array)$data['leastCoveredDiffFiles'],
            $validUntil
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->getType()->value,
            'event' => $this->event->jsonSerialize(),
            'coveragePercentage' => $this->coveragePercentage,
            'diffCoveragePercentage' => $this->diffCoveragePercentage,
            'successfulUploads' => $this->successfulUploads,
            'pendingUploads' => $this->pendingUploads,
            'tagCoverage' => $this->tagCoverage,
            'leastCoveredDiffFiles' => $this->leastCoveredDiffFiles,
            'validUntil' => $this->validUntil->format(DateTimeInterface::ATOM),
        ];
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
