<?php

namespace App\Event;

use App\Client\DynamoDbClient;
use App\Client\DynamoDbClientInterface;
use Override;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Event\EventInterface;
use Packages\Event\Model\CoverageFinalised;
use Packages\Event\Processor\EventProcessorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class CoverageFinalisedEventProcessor implements EventProcessorInterface
{
    public function __construct(
        private readonly LoggerInterface $eventHandlerLogger,
        #[Autowire(service: DynamoDbClient::class)]
        private readonly DynamoDbClientInterface $dynamoDbClient,
    ) {
    }

    #[Override]
    public function process(EventInterface $event): bool
    {
        if (!$event instanceof CoverageFinalised) {
            $this->eventHandlerLogger->warning(
                'Event skipped as it was not a CoverageFinalised event.',
                [
                    'event' => $event
                ]
            );

            return true;
        }

        $coveragePercentage = $event->getCoveragePercentage();

        $this->dynamoDbClient->setCoveragePercentage(
            $event->getProvider(),
            $event->getOwner(),
            $event->getRepository(),
            $event->getRef(),
            $coveragePercentage
        );

        $this->eventHandlerLogger->info(
            sprintf(
                'Coverage percentage (%s%%) persisted from %s.',
                $coveragePercentage,
                (string) $event
            ),
            [
                'event' => $event,
                'coveragePercentage' => $coveragePercentage,
                'provider' => $event->getProvider(),
                'owner' => $event->getOwner(),
                'repository' => $event->getRepository(),
                'ref' => $event->getRef(),
            ]
        );

        return true;
    }

    #[Override]
    public static function getEvent(): string
    {
        return Event::COVERAGE_FINALISED->value;
    }
}
