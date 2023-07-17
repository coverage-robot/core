<?php

namespace App\Tests\Query;

use App\Enum\QueryParameter;
use App\Model\QueryParameterBag;
use App\Query\CommitTagsQuery;
use App\Query\QueryInterface;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Upload;

class CommitTagsQueryTest extends AbstractQueryTestCase
{
    public function getQueryClass(): QueryInterface
    {
        return new CommitTagsQuery();
    }


    /**
     * @inheritDoc
     */
    public static function getExpectedQueries(): array
    {
        return [
            <<<SQL
            SELECT
              commit,
              ARRAY_AGG(DISTINCT tag) as tags
            FROM
              `mock-table`
            WHERE
              commit = "mock-commit"
              AND repository = "mock-repository"
              AND owner = "mock-owner"
              AND provider = "github"
            GROUP BY
              commit
            SQL,
            <<<SQL
            SELECT
              commit,
              ARRAY_AGG(DISTINCT tag) as tags
            FROM
              `mock-table`
            WHERE
              commit IN ("mock-commit", "mock-commit-2")
              AND repository = "mock-repository"
              AND owner = "mock-owner"
              AND provider = "github"
            GROUP BY
              commit
            SQL,
        ];
    }

    public static function getQueryParameters(): array
    {
        $upload = Upload::from([
            'provider' => Provider::GITHUB->value,
            'owner' => 'mock-owner',
            'repository' => 'mock-repository',
            'commit' => 'mock-commit',
            'uploadId' => 'mock-uploadId',
            'ref' => 'mock-ref',
            'parent' => [],
            'tag' => 'mock-tag',
        ]);

        $multipleCommitParameters = QueryParameterBag::fromUpload($upload);
        $multipleCommitParameters->set(
            QueryParameter::COMMIT,
            ['mock-commit', 'mock-commit-2']
        );

        return [
            QueryParameterBag::fromUpload($upload),
            $multipleCommitParameters
        ];
    }
}
