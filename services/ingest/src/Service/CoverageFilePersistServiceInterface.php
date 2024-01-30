<?php

namespace App\Service;

use App\Exception\PersistException;
use App\Model\Coverage;
use App\Service\Persist\PersistServiceInterface;
use Packages\Event\Model\Upload;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

interface CoverageFilePersistServiceInterface
{
    /**
     * Persist a parsed project's coverage file into all supported services.
     */
    public function persist(Upload $upload, Coverage $coverage): bool;
}
