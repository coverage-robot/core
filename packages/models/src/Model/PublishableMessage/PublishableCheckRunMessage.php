<?php

namespace Packages\Models\Model\PublishableMessage;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Packages\Models\Enum\PublishableMessage;
use Packages\Models\Model\Event\EventInterface;
use Packages\Models\Model\Event\GenericEvent;

class PublishableCheckRunMessage implements PublishableMessageInterface
{
    /**
     * @var PublishableCheckAnnotationMessage[]
     */
    private array $annotations;

    /**
     * @param PublishableCheckAnnotationMessage[] $annotations
     */
    public function __construct(
        private readonly EventInterface $event,
        array $annotations,
        private readonly float $coveragePercentage,
        private readonly DateTimeImmutable $validUntil,
    ) {
        $this->annotations = array_filter(
            $annotations,
            static fn(mixed $annotation) => $annotation instanceof PublishableCheckAnnotationMessage
        );
    }

    public function getEvent(): EventInterface
    {
        return $this->event;
    }

    /**
     * @return PublishableCheckAnnotationMessage[]
     */
    public function getAnnotations(): array
    {
        return $this->annotations;
    }

    public function getType(): PublishableMessage
    {
        return PublishableMessage::CheckRun;
    }

    public function getValidUntil(): DateTimeInterface
    {
        return $this->validUntil;
    }

    public function getCoveragePercentage(): float
    {
        return $this->coveragePercentage;
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
            !$validUntil
        ) {
            throw new InvalidArgumentException("Check run message is not valid.");
        }

        $annotations = array_filter(
            array_map(
                static fn(array $message) => PublishableMessageCollection::tryFromMessageUsingType($message),
                $data['annotations'] ?? []
            )
        );

        if (count($annotations) !== count($data['annotations'] ?? [])) {
            throw new InvalidArgumentException('At least one invalid message has been provided.');
        }

        return new self(
            GenericEvent::from($data['event']),
            $annotations,
            (float)$data['coveragePercentage'],
            $validUntil
        );
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

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->getType()->value,
            'event' => $this->event->jsonSerialize(),
            'annotations' => array_map(
                static fn(PublishableCheckAnnotationMessage $annotationMessage) => $annotationMessage->jsonSerialize(),
                $this->annotations
            ),
            'coveragePercentage' => $this->coveragePercentage,
            'validUntil' => $this->validUntil->format(DateTimeInterface::ATOM),
        ];
    }

    public function __toString(): string
    {
        return sprintf(
            "PublishableCheckRunMessage#%s-%s-%s",
            $this->event->getOwner(),
            $this->event->getRepository(),
            $this->event->getCommit()
        );
    }
}
