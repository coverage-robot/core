<?php

namespace Packages\Models\Model\Line;

use Packages\Models\Enum\LineType;

class Statement extends AbstractLineCoverage
{
    public function getType(): LineType
    {
        return LineType::STATEMENT;
    }
}
