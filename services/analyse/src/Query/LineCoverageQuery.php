<?php

namespace App\Query;

use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Result\LineCoverageCollectionQueryResult;
use App\Query\Trait\CarryforwardAwareTrait;
use App\Query\Trait\DiffAwareTrait;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;
use Override;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class LineCoverageQuery extends AbstractLineCoverageQuery
{
    use DiffAwareTrait;
    use CarryforwardAwareTrait;

    public function __construct(
        private readonly SerializerInterface&DenormalizerInterface $serializer,
        private readonly EnvironmentServiceInterface $environmentService
    ) {
        parent::__construct($environmentService);
    }

    #[Override]
    public function getQuery(string $table, ?QueryParameterBag $parameterBag = null): string
    {
        return <<<SQL
        {$this->getNamedQueries($table, $parameterBag)}
        SELECT
            *
        FROM
            lines
        ORDER BY
            fileName ASC,
            lineNumber ASC
        SQL;
    }

    #[Override]
    public function getUnnestQueryFiltering(string $table, ?QueryParameterBag $parameterBag = null): string
    {
        $parent = parent::getUnnestQueryFiltering($table, $parameterBag);
        $carryforwardScope = ($scope = self::getCarryforwardTagsScope(
            $parameterBag,
            self::UPLOAD_TABLE_ALIAS,
            self::LINES_TABLE_ALIAS
        )) === '' ? '' : 'OR ' . $scope;
        $lineScope = ($scope = self::getLineScope($parameterBag)) === '' ? '' : 'AND ' . $scope;

        return <<<SQL
        (
            (
                {$parent}
            )
            {$carryforwardScope}
        )
        {$lineScope}
        SQL;
    }

    /**
     * @throws GoogleException
     * @throws QueryException
     * @throws ExceptionInterface
     */
    #[Override]
    public function parseResults(QueryResults $results): LineCoverageCollectionQueryResult
    {
        if (!$results->isComplete()) {
            throw new QueryException('Query was not complete when attempting to parse results.');
        }

        $lines = $results->rows();

        /** @var LineCoverageCollectionQueryResult $results */
        $results = $this->serializer->denormalize(
            ['lines' => iterator_to_array($lines)],
            LineCoverageCollectionQueryResult::class,
            'array'
        );

        return $results;
    }
}
