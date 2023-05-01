<?php

namespace App\Service;

use App\Exception\PersistException;
use App\Model\Upload;
use App\Service\Persist\PersistServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class CoverageFilePersistService
{
    public function __construct(
        #[TaggedIterator('app.persist_service', defaultPriorityMethod: 'getPriority')]
        private readonly iterable $persistServices,
        private readonly LoggerInterface $persistServiceLogger
    ) {
    }

    /**
     * Persist a parsed project's coverage file into all supported services.
     *
     * @param Upload $upload
     * @return bool
     */
    public function persist(Upload $upload): bool
    {
        $successful = true;

        /** @var PersistServiceInterface $service */
        foreach ($this->persistServices as $service) {
            $this->persistServiceLogger->info(
                sprintf(
                    'Persisting %s into storage using %s',
                    $upload,
                    $service::class
                )
            );

            try {
                $successful = $service->persist($upload) && $successful;

                $this->persistServiceLogger->info(
                    sprintf(
                        'Persist using %s continues to be a %s',
                        $service::class,
                        $successful ? 'success' : 'fail'
                    )
                );
            } catch (PersistException $e) {
                $this->persistServiceLogger->error(
                    sprintf('Exception received while attempting to persist %s into storage.', $upload),
                    [
                        'exception' => $e
                    ]
                );

                $successful = false;
            }
        }

        return $successful;
    }
}
