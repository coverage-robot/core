<?php

namespace App\Service\History;

use Packages\Models\Model\Event\EventInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.commit_history')]
interface CommitHistoryServiceInterface
{
    /**
     * @return string[]
     */
    public function getPrecedingCommits(EventInterface $event): array;
}
