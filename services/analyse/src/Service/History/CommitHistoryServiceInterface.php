<?php

namespace App\Service\History;

use App\Model\ReportWaypoint;
use Packages\Contracts\Event\EventInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.commit_history')]
interface CommitHistoryServiceInterface
{
    /**
     * Get the commits which preceded a given commit in the tree.
     *
     * @return array{commit: string, isOnBaseRef: bool}[]
     */
    public function getPrecedingCommits(EventInterface|ReportWaypoint $event, int $page): array;
}
