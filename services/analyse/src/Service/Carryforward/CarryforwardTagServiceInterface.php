<?php

namespace App\Service\Carryforward;

use App\Model\CarryforwardTag;
use App\Model\ReportWaypoint;
use Packages\Contracts\Tag\Tag;

interface CarryforwardTagServiceInterface
{
    /**
     * Identify any tags which need to be carried forward from previous commits,
     * because they have not (yet) been uploaded to the current commit.
     *
     * @param Tag[] $existingTags
     * @return CarryforwardTag[]
     */
    public function getTagsToCarryforward(ReportWaypoint $waypoint, array $existingTags): array;
}
