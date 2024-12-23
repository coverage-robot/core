<?php

declare(strict_types=1);

namespace App\Tests\Query;

use App\Model\QueryParameterBag;
use App\Model\ReportWaypoint;
use App\Query\Result\UploadedTagsCollectionQueryResult;
use App\Query\UploadedTagsQuery;
use ArrayIterator;
use Override;
use Packages\Contracts\Provider\Provider;

final class UploadedTagsQueryTest extends AbstractQueryTestCase
{
    #[Override]
    public function getQueryClass(): string
    {
        return UploadedTagsQuery::class;
    }

    #[Override]
    public static function getQueryParameters(): array
    {
        return [
            QueryParameterBag::fromWaypoint(
                new ReportWaypoint(
                    provider: Provider::GITHUB,
                    projectId: '0193f0cd-ad49-7e14-b6d2-e88545efc889',
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    commit: 'f7e3cc3cc12c056ed8ece76216127ea1ae188d8a',
                    history: [],
                    diff: []
                )
            )
        ];
    }

    public static function getQueryResults(): array
    {
        return [
            new ArrayIterator([]),
            new ArrayIterator([
                ['tagName' => 'mock-tag-1'],
                ['tagName' => 'mock-tag-2'],
                ['tagName' => 'mock-tag-3'],
                ['tagName' => 'mock-tag-4'],
            ]),
        ];
    }

    public static function parametersDataProvider(): array
    {
        return [
            [
                new QueryParameterBag(),
                false
            ],
            [
                QueryParameterBag::fromWaypoint(
                    new ReportWaypoint(
                        provider: Provider::GITHUB,
                        projectId: '0193f0cd-ad49-7e14-b6d2-e88545efc889',
                        owner: 'mock-owner',
                        repository: 'mock-repository',
                        ref: 'mock-ref',
                        commit: 'f7e3cc3cc12c056ed8ece76216127ea1ae188d8a',
                        history: [],
                        diff: []
                    )
                ),
                true
            ],
        ];
    }
}
