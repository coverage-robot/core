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
     *      new Tag('tag-1', 'commit-sha-1'),
     *      new Tag('tag-2', 'commit-sha-1'),
     *      new Tag('tag-3', 'commit-sha-1'),
     *      new Tag('tag-4', 'commit-sha-2')
     * ]
     * ```
     * into this:
     * ```sql
     * WHERE
     * (
     *      owner = "owner" AND
     *      repository = "repository" AND
     *      provider = "provider" AND
     *      (
     *           (commit = "commit-sha-1" AND tag = "tag-1") OR
     *           (commit = "commit-sha-1" AND tag = "tag-2") OR
     *           (commit = "commit-sha-1" AND tag = "tag-3") OR
     *           (commit = "commit-sha-2" AND tag = "tag-4") OR
     *      )
     * )
     * ```
     */
    private static function getCarryforwardTagsScope(?QueryParameterBag $parameterBag): string
    {
        if ($parameterBag && $parameterBag->has(QueryParameter::CARRYFORWARD_TAGS)) {
            /** @var Tag[] $carryforwardTags */
            $carryforwardTags = $parameterBag->get(QueryParameter::CARRYFORWARD_TAGS);

            if (empty($carryforwardTags)) {
                return '';
            }

            $filtering = array_map(
                static fn(Tag $tag) => <<<SQL
                (
                    commit = "{$tag->getCommit()}" AND
                    tag = "{$tag->getName()}"
                )
                SQL,
                $carryforwardTags
            );

            $filtering = implode(' OR ', $filtering);

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
