<?php

namespace App\Service;

use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\QueryInterface;
use Doctrine\SqlFormatter\SqlFormatter;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class QueryBuilderService
{
    /**
     * @param SerializerInterface&NormalizerInterface&DenormalizerInterface $serializer
     */
    public function __construct(
        private readonly SqlFormatter $sqlFormatter,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * Build a full query string, which can be executed on BigQuery, from a Query object.
     *
     * The query string will also be formatted for readability purposes.
     *
     * @throws QueryException
     */
    public function build(QueryInterface $query, string $table, ?QueryParameterBag $parameterBag = null): string
    {
        $query->validateParameters($parameterBag);

        return $this->sqlFormatter->format(
            $query->getQuery(
                $table,
                $parameterBag
            )
        );
    }

    /**
     * Hash the contents of a query, using its parameters and the class name of the query.
     *
     * This function will also normalize the order of parameters in order to generate a
     * more predictable hash that is not affected by the order of the parameters.
     */
    public function hash(string $queryClass, ?QueryParameterBag $parameterBag): string
    {
        $parameters = [];

        if ($parameterBag) {
            /**
             * @psalm-suppress all
             */
            foreach ($parameterBag->getAll() as $key => $value) {
                $parameters[$key->value] = $value;
            }

            $parameters = $this->normaliseParameterOrder($this->serializer->normalize($parameters));
        }

        return md5(
            implode(
                '',
                [
                    $queryClass,
                    $this->serializer->serialize($parameters, 'json')
                ]
            )
        );
    }

    /**
     * Normalise the order of the parameters when in array form.
     *
     * This will help generate a much more predictable hash, which can be used for more features,
     * like caching based on parameter values.
     */
    private function normaliseParameterOrder(array $array): array
    {
        foreach ($array as &$value) {
            if (!is_array($value)) {
                continue;
            }

            $value = $this->normaliseParameterOrder($value);
        }

        if (is_array($array)) {
            // Sort the array by value, in a case-insensitive manner so that the casing
            // doesnt impact the hash
            sort($array, SORT_FLAG_CASE);
        }

        return $array;
    }
}
