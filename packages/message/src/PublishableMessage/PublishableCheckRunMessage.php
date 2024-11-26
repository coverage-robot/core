<?php

declare(strict_types=1);

namespace Packages\Message\PublishableMessage;

use DateTimeImmutable;
use Override;
use Packages\Contracts\PublishableMessage\PublishableMessage;
use Packages\Event\Model\EventInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class PublishableCheckRunMessage implements PublishableCheckRunMessageInterface
{
    public function __construct(
        private readonly EventInterface $event,
        private readonly PublishableCheckRunStatus $status,
        #[Assert\PositiveOrZero]
        #[Assert\LessThanOrEqual(100)]
        private readonly float $coveragePercentage,
        #[Assert\NotBlank(allowNull: true)]
        private readonly ?string $baseCommit = null,
        #[Assert\LessThanOrEqual(100)]
        #[Assert\GreaterThanOrEqual(-100)]
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
    public function getStatus(): PublishableCheckRunStatus
    {
        return $this->status;
    }

    #[Override]
    public function getValidUntil(): DateTimeImmutable
    {
        return $this->validUntil;
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

    #[Override]
    public function getType(): PublishableMessage
    {
        return PublishableMessage::CHECK_RUN;
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
            "PublishableCheckRunMessage#%s-%s-%s",
            $this->event->getOwner(),
            $this->event->getRepository(),
            $this->event->getCommit()
        );
    }
}
