<?php

namespace App\Query\Trait;

use App\Enum\QueryParameter;
use App\Model\QueryParameterBag;

trait DiffAwareTrait
{
    /**
     * Build a BQ query filter to scope particular queries to a particular diff (as in, lines added
     * in a file).
     *
     * In essence, convert this:
     * ```php
     * [
     *      "path/file.php" => [1, 2, 3],
     *      "path/file-2.php" => [4, 5, 6],
     * ]
     * ```
     * into this:
     * ```sql
     * WHERE
     * (
     *      (
     *              fileName LIKE "%path/file.php" AND
     *              lineNumber IN (1, 2, 3)
     *      )
     *      OR
     *      (
     *              fileName LIKE "%path/file-2.php" AND
     *              lineNumber IN (4, 5, 6)
     *      )
     * )
     * ```
     */
    private static function getLineScope(?QueryParameterBag $parameterBag, ?string $tableAlias = null): string
    {
        $tableAlias = $tableAlias ? $tableAlias . '.' : '';

        if ($parameterBag && $parameterBag->has(QueryParameter::LINE_SCOPE)) {
            /** @var array<array-key, list{int}> $fileLineNumbers */
            $fileLineNumbers = $parameterBag->get(QueryParameter::LINE_SCOPE);

            $filtering = '';
            foreach (array_keys($fileLineNumbers) as $fileName) {
                $lineNumbers = implode(',', $fileLineNumbers[$fileName]);

                $filtering .= <<<SQL
                (
                    {$tableAlias}fileName = "{$fileName}" AND
                    {$tableAlias}lineNumber IN ({$lineNumbers})
                ) OR
                SQL;
            }
            $filtering = substr($filtering, 0, -3);

            if (!empty($filtering)) {
                return <<<SQL
                (
                    $filtering
                )
                SQL;
            }
        }

        return '';
    }
}
