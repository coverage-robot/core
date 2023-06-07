<?php

namespace Packages\Models\Model;

use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;
use Packages\Models\Enum\Provider;

class Upload implements JsonSerializable
{
    private readonly DateTimeImmutable $ingestTime;

    public function __construct(
        private readonly string          $uploadId,
        private readonly Provider        $provider,
        private readonly string          $owner,
        private readonly string          $repository,
        private readonly string          $commit,
        private readonly array           $parent,
        private readonly string          $ref,
        private readonly string|int|null $pullRequest,
        private readonly string          $tag,
        ?DateTimeInterface               $ingestTime = null
    ) {
        if ($ingestTime) {
            $this->ingestTime = DateTimeImmutable::createFromInterface($ingestTime);
            return;
        }
        $this->ingestTime = new DateTimeImmutable();
    }

    public function getUploadId(): string
    {
        return $this->uploadId;
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

    public function getPullRequest(): int|string|null
    {
        return $this->pullRequest;
    }

    public function getIngestTime(): DateTimeImmutable
    {
        return $this->ingestTime;
    }

    public function getCommit(): string
    {
        return $this->commit;
    }

    public function getParent(): array
    {
        return $this->parent;
    }

    public function getTag(): string
    {
        return $this->tag;
    }

    public function __toString(): string
    {
        return 'Upload#' . $this->uploadId;
    }

    public static function from(array $data): self
    {
        // Convert all keys to lower case, in an attempt to make the interface compatible
        // with all array formats
        $data = array_change_key_case($data);

        return new self(
            (string)$data['uploadid'],
            Provider::from((string)$data['provider']),
            (string)$data['owner'],
            (string)$data['repository'],
            (string)$data['commit'],
            is_array($data['parent']) ? $data['parent'] : json_decode($data['parent'], true, 512, JSON_THROW_ON_ERROR),
            (string)$data['ref'],
            isset($data['pullrequest']) ? (int)$data['pullrequest'] : null,
            (string)$data['tag'],
            $data['ingesttime'] ? DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $data['ingesttime']) : new DateTimeImmutable()
        );
    }

    public function jsonSerialize(): array
    {
        $fields = [
            'uploadId' => $this->uploadId,
            'provider' => $this->provider->value,
            'owner' => $this->owner,
            'repository' => $this->repository,
            'ingestTime' => $this->ingestTime->format(DateTimeInterface::ATOM),
            'commit' => $this->commit,
            'parent' => $this->parent,
            'ref' => $this->ref,
            'tag' => $this->tag
        ];

        if ($this->pullRequest) {
            $fields['pullRequest'] = $this->pullRequest;
        }

        return $fields;
    }
}
