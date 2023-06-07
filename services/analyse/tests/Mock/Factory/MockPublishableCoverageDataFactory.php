<?php

namespace App\Tests\Mock\Factory;

use App\Model\PublishableCoverageData;
use App\Model\PublishableCoverageDataInterface;
use App\Service\QueryService;
use Packages\Models\Model\Upload;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MockPublishableCoverageDataFactory
{
    public static function createMock(
        TestCase $test,
        array $methodsAndReturns = [],
        ?QueryService $queryService = null,
        ?Upload $upload = null
    ): PublishableCoverageDataInterface|MockObject {
        $data = $test->getMockBuilder(PublishableCoverageData::class)
            ->setConstructorArgs([
                $queryService ?? self::getMockQueryService($test),
                $upload ?? self::getMockUpload($test)
            ])
            ->onlyMethods(
                array_keys($methodsAndReturns)
            )
            ->getMock();

        foreach ($methodsAndReturns as $method => $return) {
            $data->method($method)
                ->willReturn($return);
        }

        return $data;
    }

    private static function getMockQueryService(TestCase $test): QueryService|MockObject
    {
        return $test->getMockBuilder(QueryService::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    private static function getMockUpload(TestCase $test): Upload|MockObject
    {
        return $test->getMockBuilder(Upload::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
