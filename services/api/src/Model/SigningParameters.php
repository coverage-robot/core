<?php

namespace App\Model;

use App\Enum\ProviderEnum;
use App\Exception\SigningException;
use Exception;
use JsonSerializable;

class SigningParameters implements JsonSerializable
{
    private string $owner;
    private string $repository;
    private ProviderEnum $provider;
    private string $fileName;
    private string $tag;
    private string $commit;
    private string $parent;
    private ?string $pullRequest;

    /**
     * @param array $data
     * @throws SigningException
     */
    public function __construct(array $data)
    {
        if (
            !isset($data['owner']) ||
            !isset($data['repository']) ||
            !isset($data['provider']) ||
            !isset($data['fileName']) ||
            !isset($data['tag']) ||
            !isset($data['commit']) ||
            !isset($data['parent'])
        ) {
            throw SigningException::invalidParameters();
        }

        try {
            $this->owner = $data['owner'];
            $this->repository = $data['repository'];
            $this->provider = ProviderEnum::from($data['provider']);
            $this->fileName = $data['fileName'];
            $this->tag = $data['tag'];
            $this->commit = $data['commit'];
            $this->parent = $data['parent'];
            $this->pullRequest = $data['pullRequest'] ?? null;
        } catch (Exception $e) {
            throw SigningException::invalidParameters($e);
        }
    }

    public function getOwner(): string
    {
        return $this->owner;
    }

    public function getRepository(): string
    {
        return $this->repository;
    }

    public function getProvider(): ProviderEnum
    {
        return $this->provider;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getTag(): string
    {
        return $this->tag;
    }

    public function getCommit(): string
    {
        return $this->commit;
    }

    public function getParent(): string
    {
        return $this->parent;
    }

    public function getPullRequest(): ?string
    {
        return $this->pullRequest;
    }

    public function jsonSerialize(): array
    {
        $parameters = [
            'owner' => $this->owner,
            'repository' => $this->repository,
            'commit' => $this->commit,
            'parent' => $this->parent,
            'tag' => $this->tag,
            'provider' => $this->provider,
            'fileName' => $this->fileName
        ];

        if ($this->pullRequest) {
            $parameters['pullRequest'] = $this->pullRequest;
        }

        return $parameters;
    }
}
