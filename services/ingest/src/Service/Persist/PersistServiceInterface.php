<?php

namespace App\Service\Persist;

use App\Model\Project;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.persist')]
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
}
