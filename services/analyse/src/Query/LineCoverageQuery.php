<?php

namespace App\Query;

use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Result\LineCoverageCollectionQueryResult;
use App\Query\Trait\CarryforwardAwareTrait;
use App\Query\Trait\DiffAwareTrait;
use App\Service\EnvironmentService;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class LineCoverageQuery extends AbstractLineCoverageQuery
{
    use DiffAwareTrait;
    use CarryforwardAwareTrait;

    private const string UPLOAD_TABLE_ALIAS = 'upload';

    public function __construct(
        private readonly SerializerInterface&DenormalizerInterface $serializer,
        #[Autowire(service: EnvironmentService::class)]
        private readonly EnvironmentServiceInterface $environmentService
    ) {
        parent::__construct($environmentService);
    }

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

    public function getUnnestQueryFiltering(string $table, ?QueryParameterBag $parameterBag = null): string
    {
        $parent = parent::getUnnestQueryFiltering($table, $parameterBag);
        $carryforwardScope = ($scope = self::getCarryforwardTagsScope(
            $parameterBag,
            self::UPLOAD_TABLE_ALIAS
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
    public function parseResults(QueryResults $results): LineCoverageCollectionQueryResult
    {
        if (!$results->isComplete()) {
            throw new QueryException('Query was not complete when attempting to parse results.');
        }

        $lines = $results->rows();

        return $this->serializer->denormalize(
            ['lines' => iterator_to_array($lines)],
            LineCoverageCollectionQueryResult::class,
            'array'
        );
    }
}
