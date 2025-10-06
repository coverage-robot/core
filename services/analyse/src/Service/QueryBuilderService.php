<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\QueryInterface;
use Doctrine\SqlFormatter\SqlFormatter;
use Override;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class QueryBuilderService implements QueryBuilderServiceInterface
{
    public function __construct(
        private SqlFormatter $sqlFormatter,
        private SerializerInterface&NormalizerInterface $serializer,
        private ValidatorInterface $validator
    ) {
    }

    /**
     * Build a full query string, which can be executed on BigQuery, from a Query object.
     *
     * The query string will also be formatted for readability purposes.
     *
     * @throws QueryException
     */
    #[Override]
    public function build(QueryInterface $query, ?QueryParameterBag $parameterBag = null): string
    {
        if ($parameterBag instanceof QueryParameterBag) {
            // We've got parameters to pass through into the query, so lets first make sure
            // they're valid before we build the query
            foreach ($query->getQueryParameterConstraints() as $parameter => $constraints) {
                /** @var value-of<QueryParameterBag> $value */
                $value = $parameterBag->get(QueryParameter::from($parameter));

                $errors = $this->validator->validate($value, $constraints);

                if (count($errors) > 0) {
                    throw new QueryException(
                        sprintf(
                            'The query parameters are not suitable to execute the query: %s',
                            (string)$errors
                        )
                    );
                }
            }
        }

        return $this->sqlFormatter->format($query->getQuery($parameterBag));
    }

    /**
     * Hash the contents of a query, using its parameters and the class name of the query.
     *
     * This function will also normalize the order of parameters in order to generate a
     * more predictable hash that is not affected by the order of the parameters.
     */
    #[Override]
    public function hash(string $queryClass, ?QueryParameterBag $parameterBag): string
    {
        $parameters = [];

        if ($parameterBag instanceof QueryParameterBag) {
            $parameters = $this->normaliseParameterOrder(
                (array)$this->serializer->normalize(
                    $parameterBag->jsonSerialize()
                )
            );
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

        // Sort the array by value, in a case-insensitive manner so that the casing
        // doesnt impact the hash
        if (array_is_list($array)) {
            // Don't preserve the keys of the array if its a list of values (i.e. a list
            // of commits)
            sort($array, SORT_FLAG_CASE);
        } else {
            // Preserve the array keys if they're not a list of values (i.e. an associative
            // array, like a map)
            asort($array, SORT_FLAG_CASE);
        }

        return $array;
    }
}
