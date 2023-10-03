<?php

namespace App\Entity;

use App\Repository\JobRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Packages\Models\Enum\JobState;

#[ORM\Entity(repositoryClass: JobRepository::class)]
#[ORM\UniqueConstraint(
    columns: ['project_id', 'external_id']
)]
class Job
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'jobs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Project $project = null;

    #[ORM\Column(length: 255)]
    private ?string $commit = null;

    #[ORM\Column(type: 'string', enumType: JobState::class)]
    private ?JobState $state = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 255)]
    private ?string $externalId = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;

        return $this;
    }

    public function getCommit(): ?string
    {
        return $this->commit;
    }

    public function setCommit(string $commit): static
    {
        $this->commit = $commit;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getState(): ?JobState
    {
        return $this->state;
    }

    public function setState(JobState $state): static
    {
        $this->state = $state;

        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(string $externalId): static
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function __toString(): string
    {
        return sprintf(
            'Job#%s-%s-%s-%s',
            (int)$this->project?->getId(),
            (string)$this->commit,
            (string)$this->state?->value,
            (string)$this->externalId
        );
    }
}
