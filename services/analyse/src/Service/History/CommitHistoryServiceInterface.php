<?php

namespace App\Service\History;

use App\Model\ReportWaypoint;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.commit_history')]
interface CommitHistoryServiceInterface
{
    /**
     * Get the commits which preceded a given commit in the tree.
     *
     * @return array{commit: string, merged: bool, ref: string|null}[]
     */
    public function getPrecedingCommits(ReportWaypoint $waypoint, int $page): array;
}
