<?php

namespace App;

use Bref\SymfonyBridge\BrefKernel;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;

class Kernel extends BrefKernel
{
    use MicroKernelTrait;

    protected function getWritableCacheDirectories(): array
    {
        return [
            ...parent::getWritableCacheDirectories(),
            /**
             * The twig cache is going to be used at runtime to store the
             * compiled templates. If we don't allow this directory to be writable
             * (i.e. symlink it), then the routes which render twig templates will
             * fail at runtime.
             */
            'twig'
        ];
    }
}
