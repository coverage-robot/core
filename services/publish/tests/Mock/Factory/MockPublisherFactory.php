<?php

namespace App\Tests\Mock\Factory;

use App\Exception\PublishingNotSupportedException;
use App\Service\Publisher\PublisherServiceInterface;
use Packages\Message\PublishableMessage\PublishableMessageInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class MockPublisherFactory
{
    public static function getMockPublisher(
        TestCase $test,
        bool $supported = true,
        bool $publishSuccessfully = true
    ): MockObject {
        $mockPublisher = $test->getMockBuilder(PublisherServiceInterface::class)
            ->getMock();

        $mockPublisher->method('supports')
            ->willReturn($supported);

        if ($supported) {
            $mockPublisher->method('publish')
                ->willReturn($publishSuccessfully);
        } else {
            $mockPublisher->method('publish')
                ->willThrowException(
                    new PublishingNotSupportedException(
                        PublisherServiceInterface::class,
                        $test->getMockBuilder(PublishableMessageInterface::class)
                            ->getMock()
                    )
                );
        }

        return $mockPublisher;
    }
}
