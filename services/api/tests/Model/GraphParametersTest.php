<?php

namespace App\Tests\Model;

use App\Exception\GraphException;
use App\Exception\SigningException;
use App\Model\GraphParameters;
use Packages\Models\Enum\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GraphParametersTest extends TestCase
{
    public function testUsingGettersReturnsProperties(): void
    {
        $parameters = GraphParameters::from(
            [
                'owner' => 'owner',
                'repository' => 'repository',
                'provider' => Provider::GITHUB->value,
            ]
        );

        $this->assertEquals('owner', $parameters->getOwner());
        $this->assertEquals('repository', $parameters->getRepository());
        $this->assertEquals(Provider::GITHUB, $parameters->getProvider());
    }

    #[DataProvider('missingParametersDataProvider')]
    public function testValidatesMissingParameters(array $parameters): void
    {
        $this->expectException(GraphException::class);

        GraphParameters::from($parameters);
    }

    public static function missingParametersDataProvider(): array
    {
        return [
            [
                [
                    'repository' => 'repository',
                    'provider' => Provider::GITHUB->value,
                ],
            ],
            [
                [
                    'owner' => 'owner',
                    'provider' => Provider::GITHUB->value,
                ],
            ],
            [
                [
                    'owner' => 'owner',
                    'repository' => 'repository',
                ],
            ],
            [
                [
                    'owner' => 'owner',
                    'repository' => 'repository',
                    'provider' => 'invalid-provider',
                ],
            ]
        ];
    }
}
