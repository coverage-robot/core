<?php

namespace App\Service\Persist;

use App\Model\Upload;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\SentStamp;

class SqsPersistService implements PersistServiceInterface
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $persistServiceLogger
    ) {
    }

    public function persist(Upload $upload): bool
    {
        $envelope = $this->messageBus->dispatch($upload);

        $sent = !is_null($envelope->last(SentStamp::class));

        $this->persistServiceLogger->info(
            sprintf(
                'Persisting %s to SQS was %s',
                $upload,
                $sent ? 'successful' : 'failed'
            )
        );

        return $sent;
    }

    public static function getPriority(): int
    {
        // The message should only be persisted to the queue after BigQuery has been
        // populated.
        return BigQueryPersistService::getPriority() - 1;
    }
}
