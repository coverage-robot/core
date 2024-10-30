<?php

declare(strict_types=1);

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
    case LINES = 'LINES';

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
    case UPLOADS = 'UPLOADS';

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
    case INGEST_PARTITIONS = 'INGEST_PARTITIONS';

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
     * The ID of the project.
     *
     * ```php
     * Provider::GITHUB
     * ```
     *
     * @see \Packages\Contracts\Provider\Provider
     */
    case PROJECT_ID = 'PROJECT_ID';

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

    /**
     * The parameters that can be directly substituted into a BigQuery query.
     *
     * These parameters **must** be ones that BigQuery can parse and convert into values (i.e.
     * when an object, it must implement `ValueInterface` or be a `DateTimeInterface`).
     */
    public static function getSupportedBigQueryParameters(): array
    {
        return [
            QueryParameter::COMMIT,
            QueryParameter::OWNER,
            QueryParameter::REPOSITORY,
            QueryParameter::PROJECT_ID,
            QueryParameter::PROVIDER,
            QueryParameter::INGEST_PARTITIONS,
            QueryParameter::UPLOADS,
            QueryParameter::LIMIT
        ];
    }

    /**
     * The parameter types that can be directly substituted into a BigQuery query.
     *
     * These parameters **must** be ones that BigQuery can parse and convert into values.
     */
    public static function getBigQueryParameterType(QueryParameter $parameter): ?string
    {
        return match ($parameter) {
            QueryParameter::COMMIT,
            QueryParameter::OWNER,
            QueryParameter::REPOSITORY,
            QueryParameter::PROVIDER,
            QueryParameter::UPLOADS => 'STRING',

            QueryParameter::INGEST_PARTITIONS => 'DATE',

            QueryParameter::LIMIT => 'INT64',

            default => null
        };
    }
}
