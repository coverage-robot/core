<?php

namespace App\Model;

use App\Enum\ProviderEnum;
use JsonSerializable;

class Upload implements JsonSerializable
{
    private string $uploadId;
    private string $commit;
    private array $parent;
    private ?int $pullRequest;
    private string $owner;
    private string $repository;
    private ProviderEnum $provider;

    public function __construct(array $data)
    {
        $this->uploadId = (string)$data['uploadId'];
        $this->provider = ProviderEnum::from((string)$data['provider']);
        $this->owner = (string)$data['owner'];
        $this->repository = (string)$data['repository'];
        $this->commit = (string)$data['commit'];
        $this->parent = (array)$data['parent'];
        $this->pullRequest = isset($data['pullRequest']) ? (int)$data['pullRequest'] : null;
    }

    public function getUploadId(): string
    {
        return $this->uploadId;
    }

    public function getProvider(): ProviderEnum
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

    public function getParent(): array
    {
        return $this->parent;
    }

    public function getPullRequest(): ?int
    {
        return $this->pullRequest;
    }

    public function __toString(): string
    {
        return 'Upload#' . $this->uploadId;
    }

    public function jsonSerialize(): array
    {
        $fields = [
            'uploadId' => $this->uploadId,
            'provider' => $this->provider->value,
            'owner' => $this->owner,
            'repository' => $this->repository,
            'commit' => $this->commit,
            'parent' => $this->parent
        ];

        if ($this->pullRequest) {
            $fields['pullRequest'] = $this->pullRequest;
        }

        return $fields;
    }
}
