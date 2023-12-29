<?php

namespace Packages\Configuration\Tests\Service;

use Packages\Configuration\Service\EnvironmentService;
use Packages\Contracts\Environment\Environment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelInterface;

class EnvironmentServiceTest extends TestCase
{
    #[DataProvider('environmentDataProvider')]
    public function testGetEnvironment(
        string $environmentValue,
        Environment $expectedEnvironment
    ): void {
        $mockKernel = $this->createMock(KernelInterface::class);
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
