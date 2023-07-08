<?php

namespace App\Entity;

use App\Repository\ProjectRepository;
use Doctrine\ORM\Mapping as ORM;
use Packages\Models\Enum\Provider;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
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

    #[ORM\Column(length: 100)]
    private ?string $token = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $token): static
    {
        $this->token = $token;

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
