<?php

declare(strict_types=1);

namespace App\Query\Result;

use App\Enum\QueryResult;
use Symfony\Component\Serializer\Annotation\DiscriminatorMap;
use Symfony\Component\Serializer\Attribute\Ignore;

#[DiscriminatorMap(
    'type',
    [
        QueryResult::FILE_COVERAGE->value => FileCoverageQueryResult::class,

        QueryResult::LINE_COVERAGE->value => LineCoverageQueryResult::class,

        QueryResult::TAG_COVERAGE->value => TagCoverageQueryResult::class,

        QueryResult::TOTAL_UPLOADS->value => TotalUploadsQueryResult::class,

        QueryResult::TAG_AVAILABILITY->value => TagAvailabilityQueryResult::class,

        QueryResult::UPLOADED_TAGS->value => UploadedTagsQueryResult::class,

        /**
         * The ordering of this map is **very** important, as this dictates what the serializer will output
         * for the type property when conversed to JSON. And, by extension, this means it will impact how the
         * JSON values are deserialized back into classes.
         *
         * Specifically, because the coverage result base class is extended in other classes, it **must** go
         * after them in the discriminator map, as otherwise the type will be incorrectly serialized as "COVERAGE"
         * instead of the correct type for the child class.
         *
         * In practice, Symfony will iterate through the map in order, and the first class which is implemented
         * by the object being serialized will be used to convert the type back into the discriminator value
         * (this includes inherited classes).
         *
         * @see TagCoverageQueryResult
         * @see FileCoverageQueryResult
         */
        QueryResult::COVERAGE->value => TotalCoverageQueryResult::class,
    ]
)]
interface QueryResultInterface
{
    /**
     * The default TTL for a query cache item, in seconds - currently 6 hours.
     */
    public const int DEFAULT_QUERY_CACHE_TTL = 21600;

    /**
     * The TTL of results (in seconds) before they are considered stale.
     *
     * This will be used to cache results of queries for faster lookups, and helps to reduce the load on
     * the data warehouse.
     *
     * Results which tend to change frequently, and stale data is not acceptable should return a low TTL, and  which
     * should not be cached at all must return false.
     */
    #[Ignore]
    public function getTimeToLive(): int|false;
}
