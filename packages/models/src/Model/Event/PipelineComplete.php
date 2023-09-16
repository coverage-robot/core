<?php

namespace Packages\Models\Model\Event;

use DateTimeImmutable;
use DateTimeInterface;
use Packages\Models\Enum\Provider;

class PipelineComplete extends GenericEvent
{
    public function __construct(
        private readonly Provider $provider,
        private readonly string $owner,
        private readonly string $repository,
        private readonly string $ref,
        private readonly string $commit,
        private readonly ?string $pullRequest,
        private readonly DateTimeImmutable $completedAt
    ) {
    }

    public function getProvider(): Provider
    {
        return $this->provider;
    }

    public function getOwner(): string
    {
        return $this->owner;
    }

    public function getRepository(): string
    {
        return $this->repository;
    }

    public function getRef(): string
    {
        return $this->ref;
    }

    public function getCommit(): string
    {
        return $this->commit;
    }

    public function getPullRequest(): ?string
    {
        return $this->pullRequest;
    }

    public function getCompletedAt(): DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getEventTime(): DateTimeImmutable
    {
        return $this->completedAt;
    }

    public static function from(array $data): PipelineComplete
    {
        return new self(
            Provider::from((string)$data['provider']),
            (string)$data['owner'],
            (string)$data['repository'],
            (string)$data['ref'],
            (string)$data['commit'],
            isset($data['pullRequest']) ? (int)$data['pullRequest'] : null,
            DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $data['completedAt'])
        );
    }

    public function __toString(): string
    {
        return sprintf(
            'PipelineComplete#%s-%s-%s-%s-%s-%s',
            $this->provider->value,
            $this->owner,
            $this->repository,
            $this->ref,
            $this->commit,
            $this->pullRequest
        );
    }

    public function jsonSerialize(): array
    {
        return [
            ...parent::jsonSerialize(),
            'provider' => $this->provider->value,
            'owner' => $this->owner,
            'repository' => $this->repository,
            'ref' => $this->ref,
            'commit' => $this->commit,
            'pullRequest' => $this->pullRequest,
            'completedAt' => $this->completedAt->format(DateTimeInterface::ATOM),
        ];
    }
}
