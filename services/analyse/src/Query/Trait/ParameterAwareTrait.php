<?php

namespace App\Query\Trait;

use App\Enum\QueryParameter;

trait ParameterAwareTrait
{
    /**
     * A simple helper used to get the parameterized alias for a given query
     * parameter.
     */
    public function getAlias(QueryParameter $parameter): string
    {
        return sprintf('@%s', $parameter->value);
    }
}
