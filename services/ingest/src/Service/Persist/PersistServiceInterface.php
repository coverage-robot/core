<?php

namespace App\Service\Persist;

use App\Model\Project;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.persist_service')]
interface PersistServiceInterface
{
    /**
     * Persist a parsed coverage file to a particular service. For example, S3 or
     * BigQuery.
     *
     * @param Project $project
     * @param string $uniqueId
     * @return bool
     */
    public function persist(Project $project, string $uniqueId): bool;

    /**
     * Priories the specific persistence service when injected into the main coverage persist
     * service.
     *
     * The higher the number, the earlier the persistence service will be located in the collection.
     *
     * @return int
     */
    public static function getPriority(): int;
}
