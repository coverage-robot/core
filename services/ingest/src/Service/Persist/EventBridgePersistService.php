<?php

namespace App\Service\Persist;

use App\Client\EventBridgeEventClient;
use JsonException;
use Packages\Models\Enum\EventBus\CoverageEvent;
use Packages\Models\Model\Coverage;
use Packages\Models\Model\Upload;
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
            CoverageEvent::INGEST_SUCCESS,
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
        // The message should only be persisted to the queue after BigQuery has been
        // populated.
        return BigQueryPersistService::getPriority() - 1;
    }
}
