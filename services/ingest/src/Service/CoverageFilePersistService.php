<?php

namespace App\Service;

use App\Exception\PersistException;
use App\Model\Project;
use App\Service\Persist\PersistServiceInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class CoverageFilePersistService implements PersistServiceInterface
{
    public function __construct(
        #[TaggedIterator('app.persist_service')]
        private readonly iterable $persistServices
    ) {
    }

    public function persist(Project $project, string $uniqueId): bool
    {
        $successful = true;

        /** @var PersistServiceInterface $service */
        foreach ($this->persistServices as $service) {
            try {
                $successful = $service->persist($project, $uniqueId) && $successful;
            } catch (PersistException) {
                $successful = false;
            }
        }

        return $successful;
    }
}
