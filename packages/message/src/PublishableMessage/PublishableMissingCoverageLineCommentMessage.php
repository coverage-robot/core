<?php

declare(strict_types=1);

namespace Packages\Message\PublishableMessage;

use DateTimeImmutable;
use Override;
use Packages\Contracts\PublishableMessage\PublishableMessage;
use Packages\Event\Model\EventInterface;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class PublishableMissingCoverageLineCommentMessage implements PublishableLineCommentInterface
{
    public function __construct(
        private EventInterface $event,
        #[Assert\NotBlank]
        private string $fileName,
        private bool $startingOnMethod,
        #[Assert\GreaterThanOrEqual(1)]
        private int $startLineNumber,
        #[Assert\GreaterThanOrEqual(1)]
        private int $endLineNumber,
        private DateTimeImmutable $validUntil = new DateTimeImmutable(),
    ) {
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

    #[Override]
    public function getValidUntil(): DateTimeImmutable
    {
        return $this->validUntil;
    }

    #[Override]
    public function getType(): PublishableMessage
    {
        return PublishableMessage::MISSING_COVERAGE_LINE_COMMENT;
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
            'PublishableCheckAnnotationMessage#%s-%s-%s',
            $this->event->getOwner(),
            $this->event->getRepository(),
            $this->event->getCommit()
        );
    }
}
