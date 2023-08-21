<?php

namespace App\Model;

use App\Exception\GraphException;
use InvalidArgumentException;
use Packages\Models\Enum\Provider;
use ValueError;

class GraphParameters implements ParametersInterface
{
    public function __construct(
        private readonly string $owner,
        private readonly string $repository,
        private readonly Provider $provider,
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

    /**
     * @throws GraphException
     */
    public static function from(array $data): self
    {
        if (
            !isset($data['owner']) ||
            !isset($data['repository']) ||
            !isset($data['provider'])
        ) {
            throw GraphException::invalidParameters();
        }

        try {
            return new GraphParameters(
                (string)$data['owner'],
                (string)$data['repository'],
                Provider::from((string)$data['provider'])
            );
        } catch (ValueError $e) {
            throw GraphException::invalidParameters($e);
        }
    }
}
