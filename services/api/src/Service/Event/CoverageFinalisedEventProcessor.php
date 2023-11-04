<?php

namespace App\Service\Event;

use App\Repository\ProjectRepository;
use Packages\Event\Enum\Event;
use Packages\Event\Model\CoverageFinalised;
use Packages\Event\Model\EventInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class CoverageFinalisedEventProcessor implements EventProcessorInterface
{
    private const REFS = [
        'master',
        'main',
    ];

    /**
     * @param SerializerInterface&NormalizerInterface&DenormalizerInterface $serializer
     */
    public function __construct(
        private readonly LoggerInterface $eventHandlerLogger,
        private readonly ProjectRepository $projectRepository
    ) {
    }

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

        if (!$project) {
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

    public static function getEvent(): string
    {
        return Event::COVERAGE_FINALISED->value;
    }
}
