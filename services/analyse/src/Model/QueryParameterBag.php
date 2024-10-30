<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\QueryParameter;
use BackedEnum;
use DateTimeImmutable;
use Google\Cloud\BigQuery\Date;
use JsonSerializable;
use Override;
use Packages\Contracts\Provider\Provider;
use Symfony\Component\Serializer\Attribute\Ignore;
use WeakMap;

/**
 * @psalm-suppress MixedInferredReturnType
 * @psalm-suppress MixedReturnStatement
 */
final class QueryParameterBag implements JsonSerializable
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
            ->set(QueryParameter::PROJECT_ID, $waypoint->getProjectId())
            ->set(QueryParameter::PROVIDER, $waypoint->getProvider());
    }

    #[Override]
    public function jsonSerialize(): array
    {
        $parameters = [];

        /**
         * @psalm-suppress all
         */
        foreach ($this->getAll() as $key => $weakMap) {
            $parameters[$key->value] = $weakMap;
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

        /**
         * @psalm-suppress all
         */
        foreach ($this->getAll() as $key => $weakMap) {
            if (!in_array($key, QueryParameter::getSupportedBigQueryParameters(), true)) {
                continue;
            }

            $parameters[$key->value] = match (true) {
                $weakMap instanceof BackedEnum => $weakMap->value,
                $key === QueryParameter::INGEST_PARTITIONS => array_map(
                    static fn(DateTimeImmutable $dateTime): Date => new Date($dateTime),
                    $weakMap
                ),
                default => $weakMap
            };
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
}
