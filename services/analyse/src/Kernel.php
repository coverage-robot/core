<?php

declare(strict_types=1);

namespace App;

use Bref\SymfonyBridge\BrefKernel;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;

final class Kernel extends BrefKernel
{
    use MicroKernelTrait;
}
