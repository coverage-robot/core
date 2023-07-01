<?php

namespace App\Model;

use App\Exception\SigningException;
use Exception;
use JsonException;
use JsonSerializable;
use Packages\Models\Enum\Provider;

class SigningParameters implements JsonSerializable
{
    public function __construct(
        private readonly string $owner,
        private readonly string $repository,
        private readonly Provider $provider,
        private readonly string $fileName,
        private readonly string $projectRoot,
        private readonly string $tag,
        private readonly string $commit,
        private readonly array $parent,
        private readonly string $ref,
        private readonly ?string $pullRequest
    ) {
    }

    public function getOwner(): string
    {
        return $this->owner;
    }

    public function getRepository(): string
    {
        return $this->repository;
    }

    public function getProvider(): Provider
    {
        return $this->provider;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getProjectRoot(): string
    {
        return $this->projectRoot;
    }

    public function getTag(): string
    {
        return $this->tag;
    }

    public function getCommit(): string
    {
        return $this->commit;
    }

    public function getParent(): array
    {
        return $this->parent;
    }

    public function getRef(): string
    {
        return $this->ref;
    }

    public function getPullRequest(): ?string
    {
        return $this->pullRequest;
    }

    /**
     * @throws SigningException
     */
    public static function from(array $data): self
    {
        if (
            !isset($data['owner']) ||
            !isset($data['repository']) ||
            !isset($data['provider']) ||
            !isset($data['fileName']) ||
            !isset($data['projectRoot']) ||
            !isset($data['tag']) ||
            !isset($data['commit']) ||
            !isset($data['parent']) ||
            !isset($data['ref'])
        ) {
            throw SigningException::invalidParameters();
        }

        try {
            return new SigningParameters(
                (string)$data['owner'],
                (string)$data['repository'],
                Provider::from((string)$data['provider']),
                (string)$data['fileName'],
                (string)$data['projectRoot'],
                (string)$data['tag'],
                (string)$data['commit'],
                is_array($data['parent']) ? $data['parent'] : (array)$data['parent'],
                (string)$data['ref'],
                isset($data['pullRequest']) ? (string)$data['pullRequest'] : null
            );
        } catch (Exception $e) {
            throw SigningException::invalidParameters($e);
        }
    }

    /**
     * @throws JsonException
     */
    public function jsonSerialize(): array
    {
        $parameters = [
            'owner' => $this->owner,
            'repository' => $this->repository,
            'commit' => $this->commit,
            'parent' => json_encode($this->parent, JSON_THROW_ON_ERROR),
            'ref' => $this->ref,
            'tag' => $this->tag,
            'provider' => $this->provider,
            'fileName' => $this->fileName,
            'projectRoot' => $this->projectRoot,
        ];

        if ($this->pullRequest) {
            $parameters['pullRequest'] = $this->pullRequest;
        }

        return $parameters;
    }
}
