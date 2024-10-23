<?php

declare(strict_types=1);

namespace App\Tests\Model;

use App\Model\GraphParameters;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\TestCase;

final class GraphParametersTest extends TestCase
{
    public function testUsingGettersReturnsProperties(): void
    {
        $parameters = new GraphParameters(
            'owner',
            'repository',
            Provider::GITHUB
        );

        $this->assertEquals('owner', $parameters->getOwner());
        $this->assertEquals('repository', $parameters->getRepository());
        $this->assertEquals(Provider::GITHUB, $parameters->getProvider());
    }

    public static function missingParametersDataProvider(): array
    {
        return [
            [
                'repository',
                null,
                Provider::GITHUB
            ],
            [
                null,
                'owner',
                Provider::GITHUB
            ],
            [
                'repository',
                'owner',
                null
            ],
        ];
    }
}
