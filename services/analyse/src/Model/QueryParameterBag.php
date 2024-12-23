<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\QueryParameter;
use BackedEnum;
use DateTimeImmutable;
use Google\Cloud\BigQuery\Date;
use Iterator;
use JsonSerializable;
use Override;
use Packages\Contracts\Provider\Provider;
use SplObjectStorage;
use Symfony\Component\Serializer\Attribute\Ignore;

/**
 * @psalm-suppress MixedInferredReturnType
 * @psalm-suppress MixedReturnStatement
 *
 * @template Value of array|int|string|Provider|null
 *
 * @template-implements Iterator<QueryParameter, Value>
 */
final class QueryParameterBag implements JsonSerializable, Iterator
{
    #[Ignore]
    private SplObjectStorage $parameters;

    /**
     * @var QueryParameter[]
     */
    #[Ignore]
    private array $keys;

    private int $position = 0;

    public function __construct()
    {
        $this->parameters = new SplObjectStorage();
    }

    /**
     * @return Value
     */
    #[Ignore]
    public function get(QueryParameter $key): mixed
    {
        return $this->parameters[$key] ?? null;
    }

    public function has(QueryParameter $key): bool
    {
        return isset($this->parameters[$key]);
    }

    public function set(QueryParameter $key, array|int|string|Provider $value): self
    {
        $this->keys[] = $key;
        $this->parameters[$key] = $value;

        return $this;
    }

    public function unset(QueryParameter $key): void
    {
        unset($this->parameters[$key]);

        $this->keys = array_values(
            array_filter(
                $this->keys,
                static fn(QueryParameter $k): bool => $k !== $key
            )
        );
    }

    #[Ignore]
    public static function fromWaypoint(ReportWaypoint $waypoint): self
    {
        return (new QueryParameterBag())
            ->set(QueryParameter::COMMIT, $waypoint->getCommit())
            ->set(QueryParameter::OWNER, $waypoint->getOwner())
            ->set(QueryParameter::REPOSITORY, $waypoint->getRepository())
            ->set(QueryParameter::PROJECT_ID, $waypoint->getProjectId())
            ->set(QueryParameter::PROVIDER, $waypoint->getProvider());
    }

    #[Override]
    public function jsonSerialize(): array
    {
        $parameters = [];

        foreach ($this as $key => $value) {
            $parameters[$key->value] = $value;
        }

        return $parameters;
    }

    /**
     * Convert the parameter bag into an array of parameters which can be passed to BigQuery
     * and used as direct substitutions in a query.
     *
     * @see QueryParameter::getSupportedBigQueryParameters()
     */
    public function toBigQueryParameters(): array
    {
        $parameters = [];

        foreach ($this as $parameter => $value) {
            if (!in_array($parameter, QueryParameter::getSupportedBigQueryParameters(), true)) {
                continue;
            }

            if ($value instanceof BackedEnum) {
                $value = $value->value;
            }

            if ($parameter === QueryParameter::INGEST_PARTITIONS) {
                $value = array_map(
                    static fn(DateTimeImmutable $date): Date => new Date($date),
                    $value
                );
            }

            $parameters[$parameter->value] = $value;
        }

        return $parameters;
    }

    /**
     * Convert the parameter bag into an array of parameter types which can be passed to BigQuery
     * to tell it what the types of the substitutable parameters are.
     */
    public function toBigQueryParameterTypes(): array
    {
        $types = [];

        foreach (array_keys($this->toBigQueryParameters()) as $key) {
            $type = QueryParameter::getBigQueryParameterType(QueryParameter::from($key));

            if ($type === null) {
                // No mapped type for this parameter, so we'll let BigQuery infer it from the
                // content.
                continue;
            }

            $types[$key] = $type;
        }

        return $types;
    }

    /**
     * @return Value
     */
    #[Override]
    public function current(): mixed
    {
        return $this->get($this->key());
    }

    #[Override]
    public function next(): void
    {
        ++$this->position;
    }

    #[Override]
    public function key(): QueryParameter
    {
        return $this->keys[$this->position];
    }

    #[Override]
    public function valid(): bool
    {
        return isset($this->keys[$this->position]);
    }

    #[Override]
    public function rewind(): void
    {
        $this->position = 0;
    }
}
