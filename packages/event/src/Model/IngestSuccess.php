<?php

namespace Packages\Event\Model;

use DateTimeImmutable;
use Packages\Event\Enum\Event;
use Packages\Models\Enum\Provider;

class IngestSuccess implements EventInterface
{
    public function __construct(
        private readonly Upload $upload
    ) {
    }

    public function getProvider(): Provider
    {
        return $this->upload->getProvider();
    }

    public function getOwner(): string
    {
        return $this->upload->getOwner();
    }

    public function getRepository(): string
    {
        return $this->upload->getRepository();
    }

    public function getCommit(): string
    {
        return $this->upload->getCommit();
    }

    public function getPullRequest(): int|string|null
    {
        return $this->upload->getPullRequest();
    }

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
