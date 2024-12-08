<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\UniqueIdGeneratorService;
use PHPUnit\Framework\TestCase;

final class UniqueIdGeneratorServiceTest extends TestCase
{
    public function testGenerateDoesNotRepeat(): void
    {
        $uniqueIdGeneratorService = new UniqueIdGeneratorService();

        $uuid = $uniqueIdGeneratorService->generate();

        $this->assertNotSame($uniqueIdGeneratorService->generate(), $uuid);
    }
}
