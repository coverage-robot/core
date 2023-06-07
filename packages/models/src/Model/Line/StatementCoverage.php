<?php

namespace Packages\Models\Model\Line;

use Packages\Models\Enum\LineTypeEnum;

class StatementCoverage extends AbstractLineCoverage
{
    public function getType(): LineTypeEnum
    {
        return LineTypeEnum::STATEMENT;
    }
}
