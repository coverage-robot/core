<?php

namespace App\Service\Persist;

use App\Model\Event\IngestCompleteEvent;
use App\Model\Project;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\SentStamp;

class SqsPersistService implements PersistServiceInterface
{
    public function __construct(private readonly MessageBusInterface $messageBus)
    {
    }

    public function persist(Project $project, string $uniqueId): bool
    {
        $envelope = $this->messageBus->dispatch(
            new IngestCompleteEvent($uniqueId)
        );

        return !is_null($envelope->last(SentStamp::class));
    }

    public static function getPriority(): int
    {
        // The message should only be persisted to the queue after BigQuery has been
        // populated.
        return BigQueryPersistService::getPriority() - 1;
    }
}
