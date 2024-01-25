<?php

namespace App\Model;

use Override;
use Packages\Contracts\Provider\Provider;
use Symfony\Component\Serializer\Annotation\Ignore;

abstract class AbstractOrchestratedEvent implements OrchestratedEventInterface
{
    public function __construct(
        private readonly Provider $provider,
        private readonly string $owner,
        private readonly string $repository,
    ) {
    }

    /**
     * @inheritDoc
     */
    #[Ignore]
    #[Override]
    public function getUniqueIdentifier(): string
    {
        return $this->__toString();
    }

    /**
     * @inheritDoc
     */
    #[Ignore]
    #[Override]
    public function getUniqueRepositoryIdentifier(): string
    {
        return sprintf(
            '%s-%s-%s',
            $this->provider->value,
            $this->owner,
            $this->repository
        );
    }
}
