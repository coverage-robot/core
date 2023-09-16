<?php

namespace App\Model;

use App\Enum\QueryParameter;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Event\EventInterface;
use WeakMap;

/**
 * @psalm-suppress MixedInferredReturnType
 * @psalm-suppress MixedReturnStatement
 */
class QueryParameterBag
{
    private WeakMap $parameters;

    public function __construct()
    {
        $this->parameters = new WeakMap();
    }

    /**
     * @param QueryParameter $key
     * @return (
     *  $key is QueryParameter::COMMIT ?
     *      string :
     *      ($key is QueryParameter::EVENT ?
     *          EventInterface :
     *          ($key is QueryParameter::LINE_SCOPE ?
     *              array :
     *              ($key is QueryParameter::PROVIDER ?
     *                  Provider :
     *                  int
     *              )
     *          )
     *      )
     * )|null
     */
    public function get(QueryParameter $key): mixed
    {
        return $this->parameters[$key] ?? null;
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

        // Store the main event model in the parameter bag
        $parameters->set(QueryParameter::EVENT, $event);

        // Extract core parameters from upload model for ease of use
        $parameters->set(QueryParameter::COMMIT, $event->getCommit());
        $parameters->set(QueryParameter::OWNER, $event->getOwner());
        $parameters->set(QueryParameter::REPOSITORY, $event->getRepository());
        $parameters->set(QueryParameter::PROVIDER, $event->getProvider());

        return $parameters;
    }
}
