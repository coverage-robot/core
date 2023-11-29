<?php

namespace App\Tests\Mock\Factory;

use App\Exception\PublishException;
use App\Service\Publisher\PublisherServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MockPublisherFactory
{
    public static function getMockPublisher(
        TestCase $test,
        bool $supported = true,
        bool $publishSuccessfully = true
    ): MockObject {
        $mockPublisher = $test->createMock(PublisherServiceInterface::class);

        $mockPublisher->method('supports')
            ->willReturn($supported);

        if ($supported) {
            $mockPublisher->method('publish')
                ->willReturn($publishSuccessfully);
        } else {
            $mockPublisher->method('publish')
                ->willThrowException(PublishException::notSupportedException());
        }

        return $mockPublisher;
    }
}
