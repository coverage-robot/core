<?php

namespace App\Service\Carryforward;

use Packages\Models\Model\Tag;
use Packages\Models\Model\Upload;

interface CarryforwardTagServiceInterface
{
    /**
     * @return array<array-key, array<array-key, Tag>>
     */
    public function getTagsToCarryforward(Upload $upload): array;
}
