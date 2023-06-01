<?php

namespace Service;

use App\Enum\EnvironmentEnum;
use App\Kernel;
use App\Service\EnvironmentService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EnvironmentServiceTest extends TestCase
{
    #[DataProvider('environmentDataProvider')]
    public function testGetEnvironment(
        string $environmentValue,
        EnvironmentEnum $expectedEnvironment
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
            array_column(EnvironmentEnum::cases(), 'name'),
            array_map(
                static fn(EnvironmentEnum $environment) => [$environment->value, $environment],
                EnvironmentEnum::cases()
            )
        );
    }
}
