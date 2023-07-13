<?php

namespace App\Model;

use Packages\Models\Enum\Provider;

interface ParametersInterface
{
    public function getRepository(): string;
    public function getOwner(): string;
    public function getProvider(): Provider;
}
