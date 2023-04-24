<?php

namespace App\Model\Line;

use App\Enum\LineTypeEnum;

class StatementCoverage extends AbstractLineCoverage
{
    public function getType(): LineTypeEnum
    {
        return LineTypeEnum::STATEMENT;
    }
}
