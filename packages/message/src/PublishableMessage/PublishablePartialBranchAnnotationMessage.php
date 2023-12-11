<?php

namespace Packages\Message\PublishableMessage;

use DateTimeImmutable;
use DateTimeInterface;
use Packages\Contracts\PublishableMessage\PublishableMessage;
use Packages\Event\Model\EventInterface;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Serializer\Attribute\SerializedName;

class PublishablePartialBranchAnnotationMessage implements PublishableAnnotationInterface, PublishableMessageInterface
{
    public function __construct(
        private readonly EventInterface $event,
        private readonly string $fileName,
        private readonly int $lineNumber,
        private readonly int $totalBranches,
        private readonly int $coveredBranches,
        private readonly DateTimeImmutable $validUntil,
    ) {
    }

    public function getEvent(): EventInterface
    {
        return $this->event;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    #[SerializedName('lineNumber')]
    public function getStartLineNumber(): int
    {
        return $this->lineNumber;
    }

    #[Ignore]
    public function getEndLineNumber(): int
    {
        return $this->lineNumber;
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
