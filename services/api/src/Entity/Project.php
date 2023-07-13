<?php

namespace App\Entity;

use App\Repository\ProjectRepository;
use Doctrine\ORM\Mapping as ORM;
use Packages\Models\Enum\Provider;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\UniqueConstraint(
    columns: ['provider', 'owner', 'repository']
)]
class Project
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
}
