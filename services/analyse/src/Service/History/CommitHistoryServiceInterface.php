<?php

namespace App\Service\History;

use App\Model\ReportWaypoint;
use Packages\Contracts\Event\EventInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.commit_history')]
interface CommitHistoryServiceInterface
{
    /**
     * @return string[]
     */
    public function getPrecedingCommits(EventInterface|ReportWaypoint $event, int $page): array;
}
