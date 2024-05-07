<?php

namespace Packages\Message\PublishableMessage;

use DateTimeImmutable;
use Override;
use Packages\Contracts\PublishableMessage\PublishableMessage;
use Packages\Event\Model\EventInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class PublishablePartialBranchLineCommentMessage implements PublishableLineCommentInterface
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
    public function getFileName(): string
    {
        return $this->fileName;
    }

    #[Override]
    public function getStartLineNumber(): int
    {
        return $this->startLineNumber;
    }

    #[Override]
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

    #[Override]
    public function getValidUntil(): DateTimeImmutable
    {
        return $this->validUntil;
    }

    #[Override]
    public function getType(): PublishableMessage
    {
        return PublishableMessage::PARTIAL_BRANCH_LINE_COMMENT;
    }

    #[Override]
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

    #[Override]
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
