<?php

namespace App\Service\Persist;

use Packages\Models\Model\Coverage;
use Packages\Models\Model\Upload;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.persist_service')]
interface PersistServiceInterface
{
    /**
     * Persist a parsed coverage file to a particular service. For example, S3 or
     * BigQuery.
     *
     * @param Upload $upload
     * @param Coverage $project
     * @return bool
     */
    public function persist(Upload $upload, Coverage $project): bool;

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
