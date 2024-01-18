<?php

namespace App\Model;

use App\Enum\QueryParameter;
use JsonSerializable;
use Override;
use Packages\Contracts\Provider\Provider;
use Symfony\Component\Serializer\Attribute\Ignore;
use WeakMap;

/**
 * @psalm-suppress MixedInferredReturnType
 * @psalm-suppress MixedReturnStatement
 */
class QueryParameterBag implements JsonSerializable
{
    #[Ignore]
    private WeakMap $parameters;

    public function __construct()
    {
        $this->parameters = new WeakMap();
    }

    #[Ignore]
    public function get(QueryParameter $key): mixed
    {
        return $this->parameters[$key] ?? null;
    }

    #[Ignore]
    public function getAll(): WeakMap
    {
        return clone $this->parameters;
    }

    public function has(QueryParameter $key): bool
    {
        return isset($this->parameters[$key]);
    }

    public function set(QueryParameter $key, array|int|string|Provider $value): self
    {
        $this->parameters[$key] = $value;

        return $this;
    }

    #[Ignore]
    public static function fromWaypoint(ReportWaypoint $waypoint): self
    {
        return (new QueryParameterBag())
            ->set(QueryParameter::COMMIT, $waypoint->getCommit())
            ->set(QueryParameter::OWNER, $waypoint->getOwner())
            ->set(QueryParameter::REPOSITORY, $waypoint->getRepository())
            ->set(QueryParameter::PROVIDER, $waypoint->getProvider());
    }

    #[Override]
    public function jsonSerialize(): array
    {
        $parameters = [];

        /**
         * @psalm-suppress all
         */
        foreach ($this->getAll() as $key => $value) {
            $parameters[$key->value] = $value;
        }

        return $parameters;
    }
}
