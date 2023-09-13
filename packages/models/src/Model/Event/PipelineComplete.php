<?php

namespace Packages\Models\Model\Event;

use Packages\Models\Enum\Provider;

class PipelineComplete implements EventInterface
{
    public function __construct(
        private readonly Provider $provider,
        private readonly string $owner,
        private readonly string $repository,
        private readonly string $commit,
        private readonly ?string $pullRequest
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

    public function getCommit(): string
    {
        return $this->commit;
    }

    public function getPullRequest(): ?string
    {
        return $this->pullRequest;
    }

    public static function from(array $data): EventInterface
    {
        return new self(
            Provider::from((string)$data['provider']),
            (string)$data['owner'],
            (string)$data['repository'],
            (string)$data['commit'],
            (string)$data['pullRequest']
        );
    }

    public function __toString(): string
    {
        return sprintf(
            'PipelineComplete#%s-%s-%s-%s-%s',
            $this->provider->value,
            $this->owner,
            $this->repository,
            $this->commit,
            $this->pullRequest
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'provider' => $this->provider->value,
            'owner' => $this->owner,
            'repository' => $this->repository,
            'commit' => $this->commit,
            'pullRequest' => $this->pullRequest,
        ];
    }
}
