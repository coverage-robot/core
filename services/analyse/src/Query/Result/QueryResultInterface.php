<?php

namespace App\Query\Result;

use App\Enum\QueryResult;
use Symfony\Component\Serializer\Annotation\DiscriminatorMap;

#[DiscriminatorMap(
    'type',
    [
        QueryResult::COMMIT_COLLECTION->value => CommitCollectionQueryResult::class,
        QueryResult::COMMIT->value => CommitQueryResult::class,
        QueryResult::COVERAGE->value => CoverageQueryResult::class,
        QueryResult::FILE_COVERAGE_COLLECTION->value => FileCoverageCollectionQueryResult::class,
        QueryResult::FILE_COVERAGE->value => FileCoverageQueryResult::class,
        QueryResult::LINE_COVERAGE_COLLECTION->value => LineCoverageCollectionQueryResult::class,
        QueryResult::LINE_COVERAGE->value => LineCoverageQueryResult::class,
        QueryResult::TAG_COVERAGE_COLLECTION->value => TagCoverageCollectionQueryResult::class,
        QueryResult::TAG_COVERAGE->value => TagCoverageQueryResult::class,
        QueryResult::TOTAL_UPLOADS->value => TotalUploadsQueryResult::class,
    ]
)]
interface QueryResultInterface
{
}
