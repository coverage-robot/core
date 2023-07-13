<?php

namespace App\Service\Event;

use App\Repository\ProjectRepository;
use Bref\Event\EventBridge\EventBridgeEvent;
use JsonException;
use Packages\Models\Model\Upload;
use Psr\Log\LoggerInterface;

class AnalyseSuccessEventProcessor implements EventProcessorInterface
{
    private const REFS = [
        'master',
        'main',
    ];

    public function __construct(
        private readonly LoggerInterface $eventHandlerLogger,
        private readonly ProjectRepository $projectRepository
    ) {
    }

    /**
     * @throws JsonException
     */
    public function process(EventBridgeEvent $event): void
    {
        /** @var array $detail */
        $detail = json_decode((string)$event->getDetail(), true, JSON_THROW_ON_ERROR);

        if (
            !isset($detail['upload']) ||
            !isset($detail['coveragePercentage']) ||
            !is_numeric($detail['coveragePercentage'])
        ) {
            $this->eventHandlerLogger->warning(
                'Event skipped as it was malformed.',
                [
                    'detailType' => $event->getDetailType(),
                    'detail' => $event->getDetail(),
                ]
            );

            return;
        }

        $upload = Upload::from((array)$detail['upload']);
        $coveragePercentage = (float)$detail['coveragePercentage'];

        if (!in_array($upload->getRef(), self::REFS, true)) {
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
            'provider' => $upload->getProvider(),
            'owner' => $upload->getOwner(),
            'repository' => $upload->getRepository(),
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
                $project->getId() ?? 'null'
            ),
            [
                'detailType' => $event->getDetailType(),
                'detail' => $event->getDetail(),
            ]
        );

        $this->projectRepository->save($project, true);
    }
}
