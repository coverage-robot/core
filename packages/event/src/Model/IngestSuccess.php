<?php

namespace Packages\Event\Model;

use DateTimeImmutable;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Provider\Provider;
use Symfony\Component\Serializer\Annotation\Ignore;

class IngestSuccess implements EventInterface
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
    public function getUploadId(): string
    {
        return $this->upload->getUploadId();
    }

    #[Ignore]
    public function getProvider(): Provider
    {
        return $this->upload->getProvider();
    }

    #[Ignore]
    public function getOwner(): string
    {
        return $this->upload->getOwner();
    }

    #[Ignore]
    public function getRepository(): string
    {
        return $this->upload->getRepository();
    }

    #[Ignore]
    public function getCommit(): string
    {
        return $this->upload->getCommit();
    }

    /**
     * @return string[]
     */
    #[Ignore]
    public function getParent(): array
    {
        return $this->upload->getParent();
    }

    #[Ignore]
    public function getPullRequest(): int|string|null
    {
        return $this->upload->getPullRequest();
    }

    #[Ignore]
    public function getBaseCommit(): string|null
    {
        return $this->upload->getBaseCommit();
    }

    #[Ignore]
    public function getBaseRef(): string|null
    {
        return $this->upload->getBaseRef();
    }

    #[Ignore]
    public function getRef(): string
    {
        return $this->upload->getRef();
    }

    public function getType(): Event
    {
        return Event::INGEST_SUCCESS;
    }

    public function getEventTime(): DateTimeImmutable
    {
        return $this->eventTime;
    }

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
