<?php

namespace App\Tests\Mock\Factory;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\SerializerInterface;

class MockSerializerFactory
{
    public static function getMock(
        TestCase $test,
        array $serializeMap = [],
        array $deserializeMap = []
    ): SerializerInterface&MockObject {
        $mockSerializer = $test->getMockBuilder(SerializerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockSerializer->method('serialize')
            ->willReturnMap($serializeMap);

        $mockSerializer->method('deserialize')
            ->willReturnMap($deserializeMap);

        return $mockSerializer;
    }
}
