<?php

namespace App\Tests\Service;

use App\Service\UniqueIdGeneratorService;
use PHPUnit\Framework\TestCase;

final class UniqueIdGeneratorServiceTest extends TestCase
{
    public function testGenerateDoesNotRepeat(): void
    {
        $uniqueIdGeneratorService = new UniqueIdGeneratorService();

        $uuid = $uniqueIdGeneratorService->generate();

        $this->assertIsString($uuid);
        $this->assertNotEquals($uniqueIdGeneratorService->generate(), $uuid);
    }
}
