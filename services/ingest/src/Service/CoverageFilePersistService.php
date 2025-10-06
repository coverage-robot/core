<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\PersistException;
use App\Model\Coverage;
use App\Service\Persist\PersistServiceInterface;
use Override;
use Packages\Event\Model\Upload;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class CoverageFilePersistService implements CoverageFilePersistServiceInterface
{
    public function __construct(
        #[AutowireIterator('app.persist_service', defaultPriorityMethod: 'getPriority')]
        private iterable $persistServices,
        private LoggerInterface $persistServiceLogger
    ) {
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function persist(Upload $upload, Coverage $coverage): bool
    {
        $successful = true;

        foreach ($this->persistServices as $persistService) {
            if (!$persistService instanceof PersistServiceInterface) {
                $this->persistServiceLogger->critical(
                    'Persist service does not implement the correct interface.',
                    [
                        'persistService' => $persistService::class
                    ]
                );

                continue;
            }

            $this->persistServiceLogger->info(
                sprintf(
                    'Persisting %s into storage using %s',
                    (string)$upload,
                    $persistService::class
                )
            );

            try {
                $successful = $persistService->persist($upload, $coverage) && $successful;

                $this->persistServiceLogger->info(
                    sprintf(
                        'Persist using %s continues to be a %s',
                        $persistService::class,
                        $successful ? 'success' : 'fail'
                    )
                );
            } catch (PersistException $e) {
                $this->persistServiceLogger->error(
                    sprintf('Exception received while attempting to persist %s into storage.', (string)$upload),
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
