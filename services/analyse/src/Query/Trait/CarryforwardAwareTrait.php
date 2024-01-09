<?php

namespace App\Query\Trait;

use App\Enum\QueryParameter;
use App\Model\CarryforwardTag;
use App\Model\QueryParameterBag;

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
     *      new CarryfowardTag('tag-1', 'commit-sha-1', [new DateTimeImmutable('2021-01-01')]),
     *      new CarryfowardTag('tag-2', 'commit-sha-1', [new DateTimeImmutable('2021-01-01')]),
     *      new CarryfowardTag('tag-3', 'commit-sha-1', [new DateTimeImmutable('2021-01-01')]),
     *      new CarryfowardTag('tag-4', 'commit-sha-2', [new DateTimeImmutable('2021-01-01')])
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
     *           (commit = "commit-sha-1" AND tag = "tag-1" AND DATE(ingestTime) = '2021-01-01') OR
     *           (commit = "commit-sha-1" AND tag = "tag-2" AND DATE(ingestTime) = '2021-01-01') OR
     *           (commit = "commit-sha-1" AND tag = "tag-3" AND DATE(ingestTime) = '2021-01-01') OR
     *           (commit = "commit-sha-2" AND tag = "tag-4" AND DATE(ingestTime) = '2021-01-01') OR
     *      )
     * )
     * ```
     */
    private static function getCarryforwardTagsScope(
        ?QueryParameterBag $parameterBag,
        ?string $uploadsTableAlias = null,
        ?string $linesTableAlias = null
    ): string {
        $repositoryScope = self::getRepositoryScope($parameterBag, $uploadsTableAlias);

        $uploadsTableAlias = $uploadsTableAlias ? $uploadsTableAlias . '.' : '';

        if ($parameterBag && $parameterBag->has(QueryParameter::CARRYFORWARD_TAGS)) {
            /** @var CarryforwardTag[] $carryforwardTags */
            $carryforwardTags = $parameterBag->get(QueryParameter::CARRYFORWARD_TAGS);

            if (empty($carryforwardTags)) {
                return '';
            }

            $filtering = array_map(
                static function (CarryforwardTag $availableTag) use ($uploadsTableAlias, $linesTableAlias) {
                    return <<<SQL
                    (
                        {$uploadsTableAlias}commit = "{$availableTag->getCommit()}"
                        AND {$uploadsTableAlias}tag = "{$availableTag->getName()}"
                    )
                    SQL;
                },
                $carryforwardTags
            );

            $filtering = implode(' OR ', $filtering);

            $repositoryScope = $repositoryScope === '' ? '' : 'AND ' . $repositoryScope;

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
