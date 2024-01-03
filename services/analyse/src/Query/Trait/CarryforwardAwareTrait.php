<?php

namespace App\Query\Trait;

use App\Enum\QueryParameter;
use App\Model\QueryParameterBag;
use App\Query\Result\AvailableTagQueryResult;
use DateTimeImmutable;

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
    private static function getCarryforwardTagsScope(
        ?QueryParameterBag $parameterBag,
        ?string $uploadsTableAlias = null,
        ?string $linesTableAlias = null
    ): string {
        $repositoryScope = self::getRepositoryScope($parameterBag, $uploadsTableAlias);

        $uploadsTableAlias = $uploadsTableAlias ? $uploadsTableAlias . '.' : '';
        $linesTableAlias = $linesTableAlias ? $linesTableAlias . '.' : '';

        if ($parameterBag && $parameterBag->has(QueryParameter::CARRYFORWARD_TAGS)) {
            /** @var AvailableTagQueryResult[] $carryforwardTags */
            $carryforwardTags = $parameterBag->get(QueryParameter::CARRYFORWARD_TAGS);

            if (empty($carryforwardTags)) {
                return '';
            }

            $filtering = array_map(
                static function (AvailableTagQueryResult $tag) use ($uploadsTableAlias, $linesTableAlias) {
                    $ingestTimes = implode(
                        ',',
                        array_map(
                            static fn (DateTimeImmutable $ingestTime) =>
                                '\'{$ingestTime->format(DateTimeImmutable::ATOM)}\'',
                            $tag->getIngestTimes()
                        )
                    );

                    return <<<SQL
                    (
                        {$uploadsTableAlias}commit = "{$tag->getCommit()}"
                        AND {$uploadsTableAlias}tag = "{$tag->getName()}"
                        AND {$linesTableAlias}ingestTime IN ({$ingestTimes})
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
