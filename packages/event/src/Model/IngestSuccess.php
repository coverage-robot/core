<?php

namespace Packages\Event\Model;

use DateTimeImmutable;
use Packages\Event\Enum\Event;
use Packages\Models\Enum\Provider;
use Symfony\Component\Serializer\Annotation\Ignore;

class IngestSuccess implements EventInterface
{
    public function __construct(
        private readonly Upload $upload
    ) {
    }

    public function getUpload(): EventInterface
    {
        return $this->upload;
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

    #[Ignore]
    public function getPullRequest(): int|string|null
    {
        return $this->upload->getPullRequest();
    }

    #[Ignore]
    public function getRef(): string
    {
        return $this->upload->getRef();
    }

    #[Ignore]
    public function getType(): Event
    {
        return Event::INGEST_SUCCESS;
    }

    #[Ignore]
    public function getEventTime(): DateTimeImmutable
    {
        return $this->upload->getIngestTime();
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
