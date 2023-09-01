<?php

namespace Packages\Clients\Tests\Generator;

use Packages\Clients\Exception\ClientException;
use Packages\Clients\Generator\JwtGenerator;
use PHPUnit\Framework\TestCase;

class JwtGeneratorTest extends TestCase
{
    public function testGenerateUsingValidKey(): void
    {
        $generator = new JwtGenerator();

        $token = $generator->generate('1234', __DIR__ . '/../Fixture/mock-private-key.pem');

        $this->assertTrue($token->hasBeenIssuedBy('1234'));
    }

    public function testGenerateUsingInvalidKey(): void
    {
        $generator = new JwtGenerator();

        $this->expectException(ClientException::class);

        $generator->generate('1234', __DIR__ . '/../Fixture/invalid-path/private-key.pem');
    }
}
