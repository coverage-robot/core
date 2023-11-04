<?php

namespace App\Tests\Handler;

use App\Handler\EventHandler;
use App\Service\Event\JobStateChangeEventProcessor;
use Bref\Context\Context;
use Bref\Event\EventBridge\EventBridgeEvent;
use DateTimeImmutable;
use Packages\Event\Enum\Event;
use Packages\Event\Model\EventInterface;
use Packages\Event\Model\JobStateChange;
use Packages\Models\Enum\JobState;
use Packages\Models\Enum\Provider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ValueError;

class EventHandlerTest extends TestCase
{
    public function testHandleJobStateChangeEvent(): void
    {
        $jobStateChange = new JobStateChange(
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-ref',
            'mock-commit',
            null,
            'mock-id',
            0,
            JobState::COMPLETED,
            JobState::COMPLETED,
            true,
            new DateTimeImmutable()
        );

        $mockProcessor = $this->createMock(JobStateChangeEventProcessor::class);
        $mockProcessor->expects($this->once())
            ->method('process');

        $handler = new EventHandler(
            new NullLogger(),
            [
                Event::JOB_STATE_CHANGE->value => $mockProcessor,
            ]
        );

        $handler->handleEventBridge(
            new EventBridgeEvent(
                [
                    'detail-type' => Event::JOB_STATE_CHANGE->value,
                    'detail' => [
                        'provider' => $jobStateChange->getProvider()->value,
                        'commit' => $jobStateChange->getCommit(),
                        'owner' => $jobStateChange->getOwner(),
                        'ref' => $jobStateChange->getRef(),
                        'repository' => $jobStateChange->getRepository(),
                        'index' => $jobStateChange->getIndex(),
                        'state' => $jobStateChange->getState()->value,
                        'isInitialState' => $jobStateChange->isInitialState(),
                        'eventTime' => $jobStateChange->getEventTime()->format(DateTimeImmutable::ATOM),
                    ]
                ]
            ),
            Context::fake()
        );
    }

    public function testHandleInvalidEvent(): void
    {
        $mockProcessor = $this->createMock(JobStateChangeEventProcessor::class);
        $mockProcessor->expects($this->never())
            ->method('process');

        $handler = new EventHandler(
            new NullLogger(),
            [
                Event::JOB_STATE_CHANGE->value => $mockProcessor,
            ]
        );

        $this->expectException(ValueError::class);

        $handler->handleEventBridge(
            new EventBridgeEvent(
                [
                    'detail-type' => 'some-other-event-type',
                    'detail' => $this->createMock(EventInterface::class)
                ]
            ),
            Context::fake()
        );
    }
}
