<?php

namespace Packages\Models\Model\Line;

use Packages\Contracts\Line\LineType;

class Statement extends AbstractLine
{
    public function getType(): LineType
    {
        return LineType::STATEMENT;
    }
}
