<?php

namespace App\Service\Persist;

use App\Model\Project;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.persist')]
interface PersistServiceInterface
{
    public function persist(Project $project, string $uniqueId): bool;
}