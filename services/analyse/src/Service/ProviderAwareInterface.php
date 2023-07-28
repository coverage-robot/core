<?php

namespace App\Service;

interface ProviderAwareInterface
{
    public static function getProvider(): string;
}
