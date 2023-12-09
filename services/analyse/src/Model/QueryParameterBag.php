<?php

namespace App\Model;

use App\Enum\QueryParameter;
use JsonSerializable;
use Packages\Contracts\Event\EventInterface;
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

    public function set(QueryParameter $key, array|int|string|EventInterface|Provider $value): void
    {
        $this->parameters[$key] = $value;
    }

    public static function fromEvent(EventInterface $event): self
    {
        $parameters = new self();

        // Extract core parameters from upload model for ease of use
        $parameters->set(QueryParameter::COMMIT, $event->getCommit());
        $parameters->set(QueryParameter::OWNER, $event->getOwner());
        $parameters->set(QueryParameter::REPOSITORY, $event->getRepository());
        $parameters->set(QueryParameter::PROVIDER, $event->getProvider());

        return $parameters;
    }

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
