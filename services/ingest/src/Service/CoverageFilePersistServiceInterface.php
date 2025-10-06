<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Coverage;
use Packages\Event\Model\Upload;

interface CoverageFilePersistServiceInterface
{
    /**
     * Persist a parsed project's coverage file into all supported services.
     */
    public function persist(Upload $upload, Coverage $coverage): bool;
}
