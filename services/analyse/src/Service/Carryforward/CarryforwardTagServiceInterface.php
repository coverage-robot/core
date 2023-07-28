<?php

namespace App\Service\Carryforward;

use Packages\Models\Model\Tag;
use Packages\Models\Model\Upload;

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
