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

        $this->assertSame('owner', $parameters->getOwner());
        $this->assertSame('repository', $parameters->getRepository());
        $this->assertSame(Provider::GITHUB, $parameters->getProvider());
    }

    /**
     * @return array<int, Packages\Contracts\Provider\Provider::GITHUB[]|string[]|null[]>
     */
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
