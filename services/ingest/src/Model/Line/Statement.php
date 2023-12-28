<?php

namespace App\Model\Line;

use Packages\Contracts\Line\LineType;

class Statement extends AbstractLine
{
    public function getType(): LineType
    {
        return LineType::STATEMENT;
    }
}
