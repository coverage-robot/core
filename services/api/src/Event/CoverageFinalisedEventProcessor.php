<?php

namespace App\Event;

use App\Client\DynamoDbClient;
use App\Client\DynamoDbClientInterface;
use App\Repository\ProjectRepository;
use Override;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Event\EventInterface;
use Packages\Event\Model\CoverageFinalised;
use Packages\Event\Processor\EventProcessorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class CoverageFinalisedEventProcessor implements EventProcessorInterface
{
    private const array REFS = [
        'master',
        'main',
    ];

    public function __construct(
        private readonly LoggerInterface $eventHandlerLogger,
        private readonly ProjectRepository $projectRepository,
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

        if (!in_array($event->getRef(), self::REFS, true)) {
            $this->eventHandlerLogger->info(
                'Event skipped as it was not for a main ref.',
                [
                    'event' => $event
                ]
            );

            return true;
        }

        $project = $this->projectRepository->findOneBy([
            'provider' => $event->getProvider(),
            'owner' => $event->getOwner(),
            'repository' => $event->getRepository(),
            'enabled' => true,
        ]);

        if ($project === null) {
            $this->eventHandlerLogger->warning(
                'Event skipped as it was not related to a valid project.',
                [
                    'event' => $event
                ]
            );
            return true;
        }

        $project->setCoveragePercentage($coveragePercentage);

        $this->eventHandlerLogger->info(
            sprintf(
                'Coverage percentage (%s%%) persisted against Project#%s',
                $coveragePercentage,
                (string)$project
            ),
            [
                'event' => $event
            ]
        );

        $this->projectRepository->save($project, true);

        return true;
    }

    #[Override]
    public static function getEvent(): string
    {
        return Event::COVERAGE_FINALISED->value;
    }
}
