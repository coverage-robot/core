<?php

namespace App\Service;

use PUGX\Poser\Poser;

class BadgeService
{
    public function __construct(private readonly Poser $poser)
    {
    }

    public function getBadge(): string
    {
        return (string)$this->poser->generate('coverage', '90.1%', '00ff00', 'flat');
    }
}