<?php

namespace Packages\Contracts\Format;

enum CoverageFormat: string
{
    /**
     * Coverage which originated from an LCOV file.
     *
     * Testing frameworks:
     * - Jest
     * - Cypress
     */
    case LCOV = 'LCOV';

    /**
     * Coverage which originated from a Clover XML file.
     *
     * Testing frameworks:
     * - PHPUnit
     * - Jest
     * - Cypress
     */
    case CLOVER = 'CLOVER';
}
