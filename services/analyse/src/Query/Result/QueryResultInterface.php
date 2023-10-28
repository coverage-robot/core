<?php

namespace App\Query\Result;

use App\Enum\QueryResult;
use Symfony\Component\Serializer\Annotation\DiscriminatorMap;

#[DiscriminatorMap(
    'type',
    [
        QueryResult::FILE_COVERAGE_COLLECTION->value => FileCoverageCollectionQueryResult::class,
        QueryResult::FILE_COVERAGE->value => FileCoverageQueryResult::class,

        QueryResult::LINE_COVERAGE_COLLECTION->value => LineCoverageCollectionQueryResult::class,
        QueryResult::LINE_COVERAGE->value => LineCoverageQueryResult::class,

        QueryResult::TAG_COVERAGE_COLLECTION->value => TagCoverageCollectionQueryResult::class,
        QueryResult::TAG_COVERAGE->value => TagCoverageQueryResult::class,

        QueryResult::TOTAL_UPLOADS->value => TotalUploadsQueryResult::class,
        QueryResult::TAG_AVAILABILITY->value => TagAvailabilityQueryResult::class,

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
        QueryResult::COVERAGE->value => CoverageQueryResult::class,
    ]
)]
interface QueryResultInterface
{
}
