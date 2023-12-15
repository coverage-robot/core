<?php

namespace App\Service\Carryforward;

use App\Model\ReportWaypoint;
use Packages\Contracts\Event\EventInterface;
use Packages\Models\Model\Tag;

interface CarryforwardTagServiceInterface
{
    /**
     * Identify any tags which need to be carried forward from previous commits,
     * because they have not (yet) been uploaded to the current commit.
     *
     * @param Tag[] $existingTags
     * @return Tag[]
     */
    public function getTagsToCarryforward(EventInterface|ReportWaypoint $event, array $existingTags): array;
}
