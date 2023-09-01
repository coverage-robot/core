<?php

namespace Packages\Clients\Tests\Client\Github;

use Lcobucci\JWT\UnencryptedToken;
use Packages\Clients\Client\Github\GithubAppClient;
use Packages\Clients\Generator\JwtGenerator;
use PHPUnit\Framework\TestCase;

class GithubAppClientTest extends TestCase
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
            ->willReturn($token);

        new GithubAppClient(
            'mock-app-id',
            $jwtGenerator
        );
    }
}
