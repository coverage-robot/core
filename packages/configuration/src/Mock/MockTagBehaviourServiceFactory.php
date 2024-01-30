<?php

namespace Packages\Configuration\Mock;

use Packages\Configuration\Service\TagBehaviourService;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class MockTagBehaviourServiceFactory
{
    public static function createMock(
        TestCase $test,
        $tagsToCarryforward = []
    ): TagBehaviourService|MockObject {
        $mockTagBehaviourService = $test->getMockBuilder(TagBehaviourService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockTagBehaviourService->method('shouldCarryforwardTag')
            ->willReturnCallback(
                static fn (
                    Provider $provider,
                    string $owner,
                    string $repository,
                    string $tag
                ) => $tagsToCarryforward[$tag] ?? null
            );

        return $mockTagBehaviourService;
    }
}
