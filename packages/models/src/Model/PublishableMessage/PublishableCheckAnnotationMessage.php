<?php

namespace Packages\Models\Model\PublishableMessage;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Packages\Models\Enum\LineState;
use Packages\Models\Enum\PublishableMessage;
use Packages\Models\Model\Event\EventInterface;
use Packages\Models\Model\Event\GenericEvent;

class PublishableCheckAnnotationMessage implements PublishableMessageInterface
{
    public function __construct(
        private readonly EventInterface $event,
        private readonly string $fileName,
        private readonly int $lineNumber,
        private readonly LineState $lineState,
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

    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }

    public function getLineState(): LineState
    {
        return $this->lineState;
    }

    public function getType(): PublishableMessage
    {
        return PublishableMessage::CheckAnnotation;
    }

    public function getValidUntil(): DateTimeInterface
    {
        return $this->validUntil;
    }

    public static function from(array $data): self
    {
        $validUntil = DateTimeImmutable::createFromFormat(
            DateTimeInterface::ATOM,
            $data['validUntil'] ?? ''
        );

        if (
            !isset($data['event']) ||
            !isset($data['fileName']) ||
            !isset($data['lineNumber']) ||
            !isset($data['lineState']) ||
            !$validUntil
        ) {
            throw new InvalidArgumentException("Check annotation message is not valid.");
        }

        return new self(
            GenericEvent::from($data['event']),
            (string)$data['fileName'],
            (int)$data['lineNumber'],
            LineState::from($data['lineState']),
            $validUntil
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->getType()->value,
            'event' => $this->event->jsonSerialize(),
            'fileName' => $this->fileName,
            'lineNumber' => $this->lineNumber,
            'lineState' => $this->lineState->value,
            'validUntil' => $this->validUntil->format(DateTimeInterface::ATOM),
        ];
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
