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
    private ?Provider $Provider = null;

    #[ORM\Column(length: 255)]
    private ?string $Owner = null;

    #[ORM\Column(length: 255)]
    private ?string $Repository = null;

    #[ORM\Column(length: 255)]
    private bool $Enabled = true;

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
        return $this->Owner;
    }

    public function setOwner(string $Owner): static
    {
        $this->Owner = $Owner;

        return $this;
    }
    public function getRepository(): ?string
    {
        return $this->Repository;
    }

    public function setRepository(string $Repository): static
    {
        $this->Repository = $Repository;

        return $this;
    }

    public function getProvider(): ?Provider
    {
        return $this->Provider;
    }

    public function setProvider(?Provider $Provider): Project
    {
        $this->Provider = $Provider;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->Enabled;
    }

    public function setEnabled(bool $Enabled): Project
    {
        $this->Enabled = $Enabled;

        return $this;
    }
}
