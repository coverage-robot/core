<?php

namespace Packages\Models\Model\Line;

use Packages\Models\Enum\LineType;

class Statement extends AbstractLine
{
    public function getType(): LineType
    {
        return LineType::STATEMENT;
    }
}
