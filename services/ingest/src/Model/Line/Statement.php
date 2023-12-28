<?php

namespace App\Model\Line;

use Override;
use Packages\Contracts\Line\LineType;

class Statement extends AbstractLine
{
    #[Override]
    public function getType(): LineType
    {
        return LineType::STATEMENT;
    }
}
