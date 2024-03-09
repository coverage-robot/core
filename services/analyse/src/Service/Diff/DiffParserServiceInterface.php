<?php

namespace App\Service\Diff;

use App\Exception\CommitDiffException;
use App\Model\ReportWaypoint;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.diff_parser')]
interface DiffParserServiceInterface
{
    /**
     * Get diffs added lines for a given upload.
     *
     * The returned added lines are grouped by file, and
     * will either be from the commit, or the PR, depending
     * on the context of the upload.
     *
     * @return array<string, array<int, int>>
     *
     * @throws CommitDiffException
     */
    public function get(ReportWaypoint $waypoint): array;
}
