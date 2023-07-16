<?php

namespace App\Query\Trait;

use App\Enum\QueryParameter;
use App\Model\QueryParameterBag;
use Packages\Models\Model\Tag;

trait CarryforwardAwareTrait
{
    use ScopeAwareTrait;

    /**
     * Build a BQ query filter to scope particular queries to include a particular set of tagged coverage
     * from other commits.
     *
     * In essence, convert this:
     * ```php
     * [
     *      "commit-sha-1" => ["tag-1", "tag-2", "tag-3"],
     *      "commit-sha-2" => ["tag-2"],
     * ]
     * ```
     * into this:
     * ```sql
     * WHERE
     * (
     *      owner = "owner" AND
     *      repository = "repository" AND
     *      (
     *           (commit = "commit-sha-1" AND tag IN ("tag-1", "tag-2", "tag-3")) OR
     *           (commit = "commit-sha-2" AND tag IN ("tag-2"))
     *      )
     * )
     * ```
     */
    private static function getCarryforwardTagsScope(?QueryParameterBag $parameterBag): string
    {
        if ($parameterBag && $parameterBag->has(QueryParameter::CARRYFORWARD_TAGS)) {
            /** @var array<string, list{Tag}> $tags */
            $carryforward = $parameterBag->get(QueryParameter::CARRYFORWARD_TAGS);

            if (empty($carryforward)) {
                return '';
            }

            $filtering = '';
            foreach ($carryforward as $commit => $tags) {
                $tags = implode('","', array_map(static fn(Tag $tag) => $tag->getName(), $tags));

                $filtering .= <<<SQL
                (commit = "{$commit}" AND tag IN ("{$tags}")) OR 
                SQL;
            }
            $filtering = substr($filtering, 0, -3);

            $repositoryScope = !empty($scope = self::getRepositoryScope($parameterBag)) ? 'AND ' . $scope : '';

            return <<<SQL
            (
                (
                    {$filtering}
                )
                {$repositoryScope}
            )
            SQL;
        }

        return '';
    }
}
