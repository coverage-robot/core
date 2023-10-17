<?php

namespace App\Service\Event;

use App\Repository\ProjectRepository;
use Bref\Event\EventBridge\EventBridgeEvent;
use JsonException;
use Packages\Models\Enum\EventBus\CoverageEvent;
use Packages\Models\Model\Event\CoverageFinalised;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class NewCoverageFinalisedEventProcessor implements EventProcessorInterface
{
    private const REFS = [
        'master',
        'main',
    ];

    public function __construct(
        private readonly LoggerInterface $eventHandlerLogger,
        private readonly ProjectRepository $projectRepository,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * @throws JsonException
     */
    public function process(EventBridgeEvent $event): void
    {
        $coverageFinalised = $this->serializer->deserialize(
            $event->getDetail(),
            CoverageFinalised::class,
            'json'
        );

        $coveragePercentage = $coverageFinalised->getCoveragePercentage();

        if (!in_array($coverageFinalised->getRef(), self::REFS, true)) {
            $this->eventHandlerLogger->info(
                'Event skipped as it was not for a main ref.',
                [
                    'detailType' => $event->getDetailType(),
                    'detail' => $event->getDetail(),
                ]
            );
            return;
        }

        $project = $this->projectRepository->findOneBy([
            'provider' => $coverageFinalised->getProvider(),
            'owner' => $coverageFinalised->getOwner(),
            'repository' => $coverageFinalised->getRepository(),
            'enabled' => true,
        ]);

        if (!$project) {
            $this->eventHandlerLogger->warning(
                'Event skipped as it was not related to a valid project.',
                [
                    'detailType' => $event->getDetailType(),
                    'detail' => $event->getDetail(),
                ]
            );
            return;
        }

        $project->setCoveragePercentage($coveragePercentage);

        $this->eventHandlerLogger->info(
            sprintf(
                'Coverage percentage (%s%%) persisted against Project#%s',
                $coveragePercentage,
                (string)$project
            ),
            [
                'detailType' => $event->getDetailType(),
                'detail' => $event->getDetail(),
            ]
        );

        $this->projectRepository->save($project, true);
    }

    public static function getProcessorEvent(): string
    {
        return CoverageEvent::NEW_COVERAGE_FINALISED->value;
    }
}
