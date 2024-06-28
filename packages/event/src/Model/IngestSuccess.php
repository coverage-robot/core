<?php

namespace Packages\Event\Model;

use DateTimeImmutable;
use Override;
use Packages\Contracts\Event\BaseAwareEventInterface;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Event\ParentAwareEventInterface;
use Packages\Contracts\Provider\Provider;
use Symfony\Component\Serializer\Annotation\Ignore;
use Symfony\Component\Validator\Constraints as Assert;

final class IngestSuccess implements EventInterface, ParentAwareEventInterface, BaseAwareEventInterface
{
    public function __construct(
        private readonly Upload $upload,
        private readonly DateTimeImmutable $eventTime
    ) {
    }

    public function getUpload(): Upload
    {
        return $this->upload;
    }

    #[Ignore]
    #[Assert\NotBlank]
    #[Assert\Uuid(versions: [Assert\Uuid::V7_MONOTONIC])]
    public function getUploadId(): string
    {
        return $this->upload->getUploadId();
    }

    #[Ignore]
    #[Override]
    public function getProvider(): Provider
    {
        return $this->upload->getProvider();
    }

    #[Ignore]
    #[Override]
    public function getOwner(): string
    {
        return $this->upload->getOwner();
    }

    #[Ignore]
    #[Override]
    public function getRepository(): string
    {
        return $this->upload->getRepository();
    }

    #[Ignore]
    #[Override]
    public function getCommit(): string
    {
        return $this->upload->getCommit();
    }

    /**
     * @return string[]
     */
    #[Ignore]
    #[Override]
    public function getParent(): array
    {
        return $this->upload->getParent();
    }

    #[Ignore]
    #[Override]
    public function getPullRequest(): int|string|null
    {
        return $this->upload->getPullRequest();
    }

    #[Ignore]
    #[Override]
    public function getBaseCommit(): ?string
    {
        return $this->upload->getBaseCommit();
    }

    #[Ignore]
    #[Override]
    public function getBaseRef(): ?string
    {
        return $this->upload->getBaseRef();
    }

    #[Ignore]
    #[Override]
    public function getRef(): string
    {
        return $this->upload->getRef();
    }

    #[Override]
    public function getType(): Event
    {
        return Event::INGEST_SUCCESS;
    }

    #[Override]
    public function getEventTime(): DateTimeImmutable
    {
        return $this->eventTime;
    }

    #[Override]
    public function __toString(): string
    {
        return sprintf(
            'IngestSuccess#%s-%s-%s-%s-%s-%s',
            $this->getProvider()->value,
            $this->getOwner(),
            $this->getRepository(),
            $this->getRef(),
            $this->getCommit(),
            $this->getPullRequest() ?? ''
        );
    }
}
