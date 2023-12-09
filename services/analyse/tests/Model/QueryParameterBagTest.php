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
        $queryParameterBag = new QueryParameterBag();
        $queryParameterBag->set(
            QueryParameter::PROVIDER,
            Provider::GITHUB
        );
        $queryParameterBag->set(
            QueryParameter::LINE_SCOPE,
            []
        );
        $queryParameterBag->set(
            QueryParameter::UPLOADS_SCOPE,
            ''
        );

        $this->assertSame(
            [
                QueryParameter::PROVIDER->value => Provider::GITHUB,
                QueryParameter::LINE_SCOPE->value => [],
                QueryParameter::UPLOADS_SCOPE->value => '',
            ],
            $queryParameterBag->jsonSerialize()
        );
    }
}
