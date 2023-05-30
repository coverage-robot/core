<?php

namespace App\Tests\Mock\Factory;

use App\Model\PublishableCoverageData;
use App\Model\PublishableCoverageDataInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MockPublishableCoverageDataFactory
{
    public static function createMock(
        TestCase $test,
        array $methodsAndReturns = []
    ): PublishableCoverageDataInterface|MockObject {
        $data = $test->getMockBuilder(PublishableCoverageData::class)
            ->disableOriginalConstructor()
            ->onlyMethods(array_keys($methodsAndReturns))
            ->getMock();

        foreach ($methodsAndReturns as $method => $return) {
            $data->method($method)
                ->willReturn($return);
        }

        return $data;
    }
}