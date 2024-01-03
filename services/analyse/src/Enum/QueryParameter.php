<?php

namespace App\Enum;

enum QueryParameter: string
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
    case LINE_SCOPE = 'LINE_SCOPE';

    /**
     * The uploads to scope the queries to.
     *
     * ```php
     * [
     *      "mock-uuid-1",
     *      "mock-uuid-2",
     *      "mock-uuid-3",
     * ]
     * ```
     */
    case UPLOADS_SCOPE = 'UPLOADS_SCOPE';

    /**
     * The ingest time partitions to scope the queries to.
     *
     * ```php
     * [
     *      new DateTimeImmutable("2024-01-03 12:00:00"),
     *      new DateTimeImmutable("2024-01-04 12:00:00"),
     *      new DateTimeImmutable("2024-01-05 12:00:00"),
     * ]
     * ```
     */
    case INGEST_TIME_SCOPE = 'INGEST_TIME_SCOPE';

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
    case CARRYFORWARD_TAGS = 'CARRYFORWARD_TAGS';

    /**
     * The limit to apply on query results.
     */
    case LIMIT = 'LIMIT';

    /**
     * A commit hash, or hashes.
     *
     * ```php
     * ['commit-1', 'commit-2', 'commit-3']
     * ```
     */
    case COMMIT = 'COMMIT';

    /**
     * A repository name.
     */
    case REPOSITORY = 'REPOSITORY';

    /**
     * A repository owner.
     */
    case OWNER = 'OWNER';

    /**
     * A repository provider.
     *
     * ```php
     * Provider::GITHUB
     * ```
     *
     * @see \Packages\Contracts\Provider\Provider
     */
    case PROVIDER = 'PROVIDER';
}
