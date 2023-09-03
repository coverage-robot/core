<?php

namespace App\Query\Trait;

use App\Enum\QueryParameter;
use App\Model\QueryParameterBag;
use Packages\Models\Enum\Provider;

trait ScopeAwareTrait
{
    /**
     * Build a BQ query filter to scope particular queries to only specific repositories.
     *
     * ```sql
     * owner = "owner" AND
     * repository = "repository" AND
     * provider = "provider"
     * ```
     */
    private static function getRepositoryScope(?QueryParameterBag $parameterBag): string
    {
        $filters = [];

        if ($parameterBag && $parameterBag->has(QueryParameter::REPOSITORY)) {
            /** @var string $repository */
            $repository = $parameterBag->get(QueryParameter::REPOSITORY);

            $filters[] = <<<SQL
            repository = "{$repository}"
            SQL;
        }

        if ($parameterBag && $parameterBag->has(QueryParameter::OWNER)) {
            /** @var string $owner */
            $owner = $parameterBag->get(QueryParameter::OWNER);

            $filters[] = <<<SQL
            owner = "{$owner}"
            SQL;
        }

        if ($parameterBag && $parameterBag->has(QueryParameter::PROVIDER)) {
            /** @var Provider|null $provider */
            $provider = $parameterBag->get(QueryParameter::PROVIDER);

            $filters[] = <<<SQL
            provider = "{$provider?->value}"
            SQL;
        }

        return implode("\nAND ", $filters);
    }

    /**
     * Build a BQ query filter to scope particular queries to only specific commit(s).
     *
     * This can be either a single commit, or an array of commits.
     *
     * For example, convert this:
     * ```php
     * [
     *     'commit-sha-1',
     *     'commit-sha-2',
     *     'commit-sha-3',
     * ]
     * ```
     * into:
     * ```sql
     * commit IN ('commit-sha-1', 'commit-sha-2', 'commit-sha-3')
     * ```
     */
    private static function getCommitScope(?QueryParameterBag $parameterBag): string
    {
        if ($parameterBag && $parameterBag->has(QueryParameter::COMMIT)) {
            /** @var string|string[] $commits */
            $commits = $parameterBag->get(QueryParameter::COMMIT);

            if (is_string($commits)) {
                return <<<SQL
                commit = "{$commits}"
                SQL;
            }

            $commits = implode('","', $commits);

            return <<<SQL
            commit IN ("{$commits}")
            SQL;
        }

        return '';
    }

    /**
     * Build a simple BigQuery limit clause.
     *
     * For example:
     * ```sql
     * LIMIT 100
     * ```
     */
    private static function getLimit(?QueryParameterBag $parameterBag): string
    {
        if ($parameterBag && $parameterBag->has(QueryParameter::LIMIT)) {
            return 'LIMIT ' . (string)$parameterBag->get(QueryParameter::LIMIT);
        }

        return '';
    }

    private static function getSuccessfulUploadsScope(string $table, ?QueryParameterBag $parameterBag): string
    {
        return <<<SQL
            totalLines >= (
                SELECT
                    COUNT(uploadId)
                FROM
                    `$table`
                WHERE
                    uploadId = "{$parameterBag->get(QueryParameter::UPLOAD)->getUploadId()}"
                GROUP BY
                    uploadId
            )
        SQL;
    }
}
