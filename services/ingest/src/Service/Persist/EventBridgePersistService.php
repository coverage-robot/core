<?php

namespace App\Service\Persist;

use App\Client\EventBridgeEventClient;
use JsonException;
use Packages\Event\Enum\Event;
use Packages\Models\Model\Coverage;
use Packages\Event\Model\Upload;
use Psr\Log\LoggerInterface;

class EventBridgePersistService implements PersistServiceInterface
{
    public function __construct(
        private readonly EventBridgeEventClient $eventBridgeEventService,
        private readonly LoggerInterface $sqsPersistServiceLogger
    ) {
    }

    /**
     * @throws JsonException
     */
    public function persist(Upload $upload, Coverage $coverage): bool
    {
        $published = $this->eventBridgeEventService->publishEvent(
            Event::INGEST_SUCCESS,
            $upload
        );

        $this->sqsPersistServiceLogger->info(
            sprintf(
                'Persisting %s to EventBridge was %s',
                (string)$upload,
                $published ? 'successful' : 'failed'
            )
        );

        return $published;
    }

    public static function getPriority(): int
    {
        // The message should only be persisted to the queue after the data has been loaded
        // into BigQuery.
        return GcsPersistService::getPriority() - 1;
    }
}
