<?php

namespace App\Entity;

use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Packages\Contracts\Provider\Provider;
use Stringable;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\UniqueConstraint(
    columns: ['provider', 'owner', 'repository']
)]
class Project implements Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', enumType: Provider::class)]
    private ?Provider $provider = null;

    #[ORM\Column(length: 255)]
    private ?string $owner = null;

    #[ORM\Column(length: 255)]
    private ?string $repository = null;

    #[ORM\Column(length: 255)]
    private bool $enabled = true;

    /**
     * A unique token used to authenticate requests for new uploads.
     *
     * This token **is** sensitive (as it provides access to upload new reports) and should always be stored
     * securely, and not in plain text by users.
     */
    #[ORM\Column(length: 100, unique: true)]
    private ?string $uploadToken = null;

    /**
     * A unique token used to authenticate requests for graphs and badges.
     *
     * This token **is not** sensitive (as it is provided in the URL parameters), but only has access
     * to non-sensitive content.
     */
    #[ORM\Column(length: 100, unique: true)]
    private ?string $graphToken = null;

    #[ORM\Column(options: ['default' => null], nullable: true)]
    private ?float $coveragePercentage = null;

    /**
     * @var Collection<int, Job>
     */
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: Job::class, orphanRemoval: true)]
    private Collection $jobs;

    public function __construct()
    {
        $this->jobs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUploadToken(): ?string
    {
        return $this->uploadToken;
    }

    public function setUploadToken(string $uploadToken): static
    {
        $this->uploadToken = $uploadToken;

        return $this;
    }

    public function getGraphToken(): ?string
    {
        return $this->graphToken;
    }

    public function setGraphToken(?string $graphToken): Project
    {
        $this->graphToken = $graphToken;
        return $this;
    }

    public function getOwner(): ?string
    {
        return $this->owner;
    }

    public function setOwner(string $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getRepository(): ?string
    {
        return $this->repository;
    }

    public function setRepository(string $repository): static
    {
        $this->repository = $repository;

        return $this;
    }

    public function getProvider(): ?Provider
    {
        return $this->provider;
    }

    public function setProvider(?Provider $provider): Project
    {
        $this->provider = $provider;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): Project
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getCoveragePercentage(): ?float
    {
        return $this->coveragePercentage;
    }

    public function setCoveragePercentage(?float $coveragePercentage): Project
    {
        $this->coveragePercentage = $coveragePercentage;
        return $this;
    }

    /**
     * @return Collection<int, Job>
     */
    public function getJobs(): Collection
    {
        return $this->jobs;
    }

    public function addJob(Job $job): static
    {
        if (!$this->jobs->contains($job)) {
            $this->jobs->add($job);
            $job->setProject($this);
        }

        return $this;
    }

    public function removeJob(Job $job): static
    {
        // set the owning side to null (unless already changed)
        if ($this->jobs->removeElement($job) && $job->getProject() === $this) {
            $job->setProject(null);
        }

        return $this;
    }

    #[Override]
    public function __toString(): string
    {
        $projectId = $this->id;

        if ($projectId === null) {
            return sprintf(
                'Project#%s-%s-%s',
                (string)$this->provider?->value,
                (string)$this->owner,
                (string)$this->repository
            );
        }

        return sprintf(
            'Project#%s',
            $projectId
        );
    }
}
