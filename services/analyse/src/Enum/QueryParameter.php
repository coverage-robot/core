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
     * The tags to carry forward from previous commits (parents to the current upload)
     * ```
     * [
     *     new Tag('tag-1', 'commit-1'),
     *     new Tag('tag-2', 'commit-1'),
     *     new Tag('tag-3', 'commit-3'),
     * ]
     * ```
     */
    case CARRYFORWARD_TAGS;

    /**
     * The limit to apply on query results.
     */
    case LIMIT;

    /**
     * A full upload model.
     */
    case UPLOAD;

    /**
     * A commit hash, or hashes.
     *
     * ```php
     * ['commit-1', 'commit-2', 'commit-3']
     * ```
     */
    case COMMIT;

    /**
     * A repository name.
     */
    case REPOSITORY;

    /**
     * A repository owner.
     */
    case OWNER;

    /**
     * A repository provider.
     *
     * ```php
     * Provider::GITHUB
     * ```
     *
     * @see \Packages\Models\Enum\Provider
     */
    case PROVIDER;
}
