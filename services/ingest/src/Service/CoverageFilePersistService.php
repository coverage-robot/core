<?php

namespace App\Service;

use App\Exception\PersistException;
use App\Model\Project;
use App\Service\Persist\PersistServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class CoverageFilePersistService implements PersistServiceInterface
{
    public function __construct(
        #[TaggedIterator('app.persist_service')]
        private readonly iterable $persistServices,
        private readonly LoggerInterface $persistServiceLogger
    ) {
    }

    /**
     * Persist a parsed project's coverage file into all supported services.
     *
     * @param Project $project
     * @param string $uniqueId
     * @return bool
     */
    public function persist(Project $project, string $uniqueId): bool
    {
        $successful = true;

        /** @var PersistServiceInterface $service */
        foreach ($this->persistServices as $service) {
            try {
                $successful = $service->persist($project, $uniqueId) && $successful;
            } catch (PersistException $e) {
                $this->persistServiceLogger->error(
                    sprintf('Exception received while attempting to persist %s into storage.', $uniqueId),
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
