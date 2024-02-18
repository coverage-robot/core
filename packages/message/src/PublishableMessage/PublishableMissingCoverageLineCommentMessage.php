<?php

namespace Packages\Message\PublishableMessage;

use DateTimeImmutable;
use Packages\Contracts\PublishableMessage\PublishableMessage;
use Packages\Event\Model\EventInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class PublishableMissingCoverageLineCommentMessage implements PublishableLineCommentInterface
{
    public function __construct(
        private readonly EventInterface $event,
        #[Assert\NotBlank]
        private readonly string $fileName,
        private readonly bool $startingOnMethod,
        #[Assert\GreaterThanOrEqual(1)]
        private readonly int $startLineNumber,
        #[Assert\GreaterThanOrEqual(1)]
        private readonly int $endLineNumber,
        private ?DateTimeImmutable $validUntil = null,
    ) {
        if (!$this->validUntil instanceof \DateTimeImmutable) {
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

    /**
     * Whether or not this annotation is starting on a method.
     *
     * This helps us to add contextual information to the annotation, such as changing
     * wording.
     */
    public function isStartingOnMethod(): bool
    {
        return $this->startingOnMethod;
    }

    public function getStartLineNumber(): int
    {
        return $this->startLineNumber;
    }

    public function getEndLineNumber(): int
    {
        return $this->endLineNumber;
    }

    public function getValidUntil(): DateTimeImmutable
    {
        return $this->validUntil;
    }

    public function getType(): PublishableMessage
    {
        return PublishableMessage::MISSING_COVERAGE_LINE_COMMENT;
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
