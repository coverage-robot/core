<?php

namespace App\Tests\Model;

use App\Enum\QueryParameter;
use App\Model\QueryParameterBag;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\TestCase;

class QueryParameterBagTest extends TestCase
{
    public function testSerialize(): void
    {
        $queryParameterBag = (new QueryParameterBag())
            ->set(
                QueryParameter::PROVIDER,
                Provider::GITHUB
            )
            ->set(
                QueryParameter::LINES,
                []
            )
            ->set(
                QueryParameter::UPLOADS,
                ''
            );

        $this->assertSame(
            [
                QueryParameter::PROVIDER->value => Provider::GITHUB,
                QueryParameter::LINES->value => [],
                QueryParameter::UPLOADS->value => '',
            ],
            $queryParameterBag->jsonSerialize()
        );
    }

    public function testBigQueryParameterSerialization(): void
    {
        $queryParameterBag = (new QueryParameterBag())
            ->set(
                QueryParameter::PROVIDER,
                Provider::GITHUB
            )
            ->set(
                QueryParameter::LINES,
                []
            )
            ->set(
                QueryParameter::UPLOADS,
                ''
            )
            ->set(
                QueryParameter::CARRYFORWARD_TAGS,
                []
            );

        $this->assertSame(
            [
                QueryParameter::PROVIDER->value => Provider::GITHUB->value,
                QueryParameter::UPLOADS->value => '',
            ],
            $queryParameterBag->toBigQueryParameters()
        );
    }
}
