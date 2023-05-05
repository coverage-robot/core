<?php

namespace App\Model;

use App\Enum\ProviderEnum;

class Upload
{
    private string $uploadId;
    private string $commit;
    private string $parent;
    private int $pullRequest;
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
        $this->parent = (string)$data['parent'];
        $this->pullRequest = (int)$data['pullRequest'];
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

    public function getParent(): string
    {
        return $this->parent;
    }

    public function getPullRequest(): int
    {
        return $this->pullRequest;
    }

    public function __toString(): string
    {
        return "Upload #" . $this->uploadId;
    }
}
