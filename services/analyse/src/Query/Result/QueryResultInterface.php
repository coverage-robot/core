<?php

namespace App\Query\Result;

use Symfony\Component\Serializer\Annotation\DiscriminatorMap;

#[DiscriminatorMap(
    'type',
    [
        CommitCollectionQueryResult::class => CommitCollectionQueryResult::class,
        CommitQueryResult::class => CommitQueryResult::class,
        CoverageQueryResult::class => CoverageQueryResult::class,
        FileCoverageCollectionQueryResult::class => FileCoverageCollectionQueryResult::class,
        FileCoverageQueryResult::class => FileCoverageQueryResult::class,
        LineCoverageCollectionQueryResult::class => LineCoverageCollectionQueryResult::class,
        LineCoverageQueryResult::class => LineCoverageQueryResult::class,
        TagCoverageCollectionQueryResult::class => TagCoverageCollectionQueryResult::class,
        TagCoverageQueryResult::class => TagCoverageQueryResult::class,
        TotalUploadsQueryResult::class => TotalUploadsQueryResult::class,
    ]
)]
interface QueryResultInterface
{
}
