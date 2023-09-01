<?php

namespace Packages\Clients\Tests\Generator;

use Packages\Clients\Exception\ClientException;
use Packages\Clients\Generator\JwtGenerator;
use PHPUnit\Framework\TestCase;

class JwtGeneratorTest extends TestCase
{
    public function testGenerateUsingValidKey(): void
    {
        $generator = new JwtGenerator(__DIR__ . '/../Fixture/mock-private-key.pem');

        $token = $generator->generate('1234');

        $this->assertTrue($token->hasBeenIssuedBy('1234'));
    }

    public function testGenerateUsingInvalidKey(): void
    {
        $generator = new JwtGenerator(__DIR__ . '/../Fixture/invalid-path/private-key.pem');

        $this->expectException(ClientException::class);

        $generator->generate('1234');
    }
}
