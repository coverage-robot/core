<?php

namespace App\Service\Carryforward;

use Packages\Models\Model\Event\Upload;
use Packages\Models\Model\Tag;

interface CarryforwardTagServiceInterface
{
    /**
     * Identify any tags which need to be carried forward from previous commits,
     * because they have not (yet) been uploaded to the current commit.
     *
     * @return Tag[]
     */
    public function getTagsToCarryforward(Upload $upload): array;
}
