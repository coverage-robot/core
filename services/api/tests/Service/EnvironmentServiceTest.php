<?php

namespace App\Tests\Service;

use App\Kernel;
use App\Service\EnvironmentService;
use Packages\Contracts\Environment\Environment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EnvironmentServiceTest extends TestCase
{
    #[DataProvider('environmentDataProvider')]
    public function testGetEnvironment(
        string $environmentValue,
        Environment $expectedEnvironment
    ): void {
        $mockKernel = $this->createMock(Kernel::class);
        $mockKernel->expects($this->once())
            ->method('getEnvironment')
            ->willReturn($environmentValue);

        $environmentService = new EnvironmentService($mockKernel);
        $this->assertEquals(
            $expectedEnvironment,
            $environmentService->getEnvironment()
        );
    }

    public static function environmentDataProvider(): array
    {
        return array_combine(
            array_column(Environment::cases(), 'name'),
            array_map(
                static fn(Environment $environment) => [$environment->value, $environment],
                Environment::cases()
            )
        );
    }
}
