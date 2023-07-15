<?php

namespace App\Enum;

enum QueryParameter
{
    /**
     * The added lines from multiple file's diffs.
     *
     * ```php
     * [
     *      "path/file.php" => [1, 2, 3],
     *      "path/file-2.php" => [4, 5, 6],
     * ]
     * ```
     */
    case LINE_SCOPE;

    /**
     * ```
     * [
     *     "commit-sha" => ["tag-name", "tag-name-2"]
     *     "commit-sha-2" => ["tag-name-3"],
     *     "commit-sha-3" => ["tag-name-4", "tag-name-5"],
     * ]
     * ```
     */
    case CARRYFORWARD_TAGS;

    /**
     * The limit to apply on query results.
     */
    case LIMIT;

    case UPLOAD;

    case COMMIT;

    case REPOSITORY;

    case OWNER;

    case PROVIDER;

    case REF;
}
