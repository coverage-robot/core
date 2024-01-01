<?php

namespace Packages\Message\PublishableMessage;

use DateTimeImmutable;
use DateTimeInterface;
use Packages\Contracts\PublishableMessage\PublishableMessage;
use Packages\Event\Model\EventInterface;
use Symfony\Component\Validator\Constraints as Assert;

class PublishablePartialBranchAnnotationMessage implements PublishableAnnotationInterface, PublishableMessageInterface
{
    public function __construct(
        private readonly EventInterface $event,
        #[Assert\NotBlank]
        private readonly string $fileName,
        #[Assert\GreaterThanOrEqual(1)]
        private readonly int $startLineNumber,
        #[Assert\GreaterThanOrEqual(1)]
        private readonly int $endLineNumber,
        #[Assert\PositiveOrZero]
        private readonly int $totalBranches,
        #[Assert\PositiveOrZero]
        private readonly int $coveredBranches,
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

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getStartLineNumber(): int
    {
        return $this->startLineNumber;
    }

    public function getEndLineNumber(): int
    {
        return $this->endLineNumber;
    }

    public function getTotalBranches(): int
    {
        return $this->totalBranches;
    }

    public function getCoveredBranches(): int
    {
        return $this->coveredBranches;
    }

    public function getValidUntil(): DateTimeInterface
    {
        return $this->validUntil;
    }

    public function getType(): PublishableMessage
    {
        return PublishableMessage::PARTIAL_BRANCH_ANNOTATION;
    }

    public function getMessageGroup(): string
    {
        return md5(
            implode('', [
                $this->event->getProvider()->value,
                $this->event->getOwner(),
                $this->event->getRepository(),
                $this->event->getCommit()
            ])
        );
    }

    public function __toString(): string
    {
        return sprintf(
            "PublishableCheckAnnotationMessage#%s-%s-%s",
            $this->event->getOwner(),
            $this->event->getRepository(),
            $this->event->getCommit()
        );
    }
}
