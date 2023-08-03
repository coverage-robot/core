<?php

namespace App\Query;

use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Result\IntegerQueryResult;
use App\Query\Trait\ScopeAwareTrait;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;
use Packages\Models\Model\Upload;

class TotalUploadsQuery implements QueryInterface
{
    use ScopeAwareTrait;

    public function getQuery(string $table, ?QueryParameterBag $parameterBag = null): string
    {
        $commitScope = self::getCommitScope($parameterBag);
        $repositoryScope = self::getRepositoryScope($parameterBag);

        // To avoid any race conditions, the only uploads which should be included are those _before_ the current
        // upload we're analysing. This is because we cannot guarantee the completeness of any coverage data which
        // is after what we're currently uploading.
        $ingestTimeScope = sprintf(
            'ingestTime <= "%s"',
            $parameterBag->get(QueryParameter::UPLOAD)
                ->getIngestTime()
                ->format('Y-m-d H:i:s')
        );

        return <<<SQL
        SELECT
            COUNT(DISTINCT uploadId) as totalUploads
        FROM
            `$table`
        WHERE
            {$commitScope} AND
            {$ingestTimeScope} AND
            {$repositoryScope}
        SQL;
    }

    public function getNamedQueries(string $table, ?QueryParameterBag $parameterBag = null): string
    {
        return '';
    }

    /**
     * @throws GoogleException
     * @throws QueryException
     */
    public function parseResults(QueryResults $results): IntegerQueryResult
    {
        if (!$results->isComplete()) {
            throw new QueryException('Query was not complete when attempting to parse results.');
        }

        /** @var array $row */
        $row = $results->rows()
            ->current();

        if (is_int($row['totalUploads'])) {
            return IntegerQueryResult::from($row['totalUploads']);
        }

        throw QueryException::typeMismatch(gettype($row['totalUploads']), 'int');
    }

    public function validateParameters(?QueryParameterBag $parameterBag = null): void
    {
        if (
            !$parameterBag ||
            !$parameterBag->has(QueryParameter::UPLOAD) ||
            !($parameterBag->get(QueryParameter::UPLOAD) instanceof Upload)
        ) {
            throw QueryException::invalidParameters(QueryParameter::UPLOAD);
        }
    }
}
