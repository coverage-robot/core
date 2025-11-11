<?php

declare(strict_types=1);

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

    /**
     * Coverage which originated from the Go CLI using the
     * Cover flag.
     *
     * @see https://go.dev/blog/integration-test-coverage
     */
    case GO_COVER = 'GO_COVER';
}
