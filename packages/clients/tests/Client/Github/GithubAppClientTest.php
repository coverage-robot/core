<?php

namespace Packages\Clients\Tests\Client\Github;

use Github\Api\Apps;
use Lcobucci\JWT\UnencryptedToken;
use Packages\Clients\Client\Github\GithubAppClient;
use Packages\Clients\Generator\JwtGenerator;
use Packages\Telemetry\Service\MetricServiceInterface;
use PHPUnit\Framework\TestCase;

final class GithubAppClientTest extends TestCase
{
    public function testAuthenticatingAsApp(): void
    {
        $token = $this->createMock(UnencryptedToken::class);
        $token->expects($this->once())
            ->method('toString')
            ->willReturn('test-token');

        $jwtGenerator = $this->createMock(JwtGenerator::class);

        $jwtGenerator->expects($this->once())
            ->method('generate')
            ->with('mock-app-id', 'some-path/file.pem')
            ->willReturn($token);

        new GithubAppClient(
            'mock-app-id',
            'some-path/file.pem',
            $jwtGenerator,
            $this->createMock(MetricServiceInterface::class)
        );
    }

    public function testCommonEndpointsAreAvailable(): void
    {
        $client = new GithubAppClient(
            'mock-app-id',
            'some-path/file.pem',
            $this->createMock(JwtGenerator::class),
            $this->createMock(MetricServiceInterface::class)
        );

        $this->assertInstanceOf(Apps::class, $client->apps());
    }
}
