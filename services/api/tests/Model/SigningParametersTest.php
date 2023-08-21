<?php

namespace App\Tests\Model;

use App\Exception\SigningException;
use App\Model\SigningParameters;
use Packages\Models\Enum\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SigningParametersTest extends TestCase
{
    public function testUsingGettersReturnsProperties(): void
    {
        $parameters = SigningParameters::from(
            [
                'owner' => 'owner',
                'repository' => 'repository',
                'provider' => Provider::GITHUB->value,
                'fileName' => 'fileName',
                'projectRoot' => 'projectRoot',
                'tag' => 'tag',
                'commit' => 'commit',
                'parent' => ['parent'],
                'ref' => 'ref',
                'pullRequest' => 'pullRequest',
            ]
        );

        $this->assertEquals('owner', $parameters->getOwner());
        $this->assertEquals('repository', $parameters->getRepository());
        $this->assertEquals(Provider::GITHUB, $parameters->getProvider());
        $this->assertEquals('fileName', $parameters->getFileName());
        $this->assertEquals('projectRoot', $parameters->getProjectRoot());
        $this->assertEquals('tag', $parameters->getTag());
        $this->assertEquals('commit', $parameters->getCommit());
        $this->assertEquals(['parent'], $parameters->getParent());
        $this->assertEquals('ref', $parameters->getRef());
        $this->assertEquals('pullRequest', $parameters->getPullRequest());
    }

    #[DataProvider('missingParametersDataProvider')]
    public function testValidatesMissingParameters(array $parameters): void
    {
        $this->expectException(SigningException::class);

        SigningParameters::from($parameters);
    }

    public static function missingParametersDataProvider(): array
    {
        return [
            [
                [
                    'repository' => 'repository',
                    'provider' => Provider::GITHUB->value,
                    'fileName' => 'fileName',
                    'projectRoot' => 'projectRoot',
                    'tag' => 'tag',
                    'commit' => 'commit',
                    'parent' => ['parent'],
                    'ref' => 'ref',
                    'pullRequest' => 'pullRequest',
                ],
            ],
            [
                [
                    'owner' => 'owner',
                    'provider' => Provider::GITHUB->value,
                    'fileName' => 'fileName',
                    'projectRoot' => 'projectRoot',
                    'tag' => 'tag',
                    'commit' => 'commit',
                    'parent' => ['parent'],
                    'ref' => 'ref',
                    'pullRequest' => 'pullRequest',
                ],
            ],
            [
                [
                    'owner' => 'owner',
                    'repository' => 'repository',
                    'fileName' => 'fileName',
                    'projectRoot' => 'projectRoot',
                    'tag' => 'tag',
                    'commit' => 'commit',
                    'parent' => ['parent'],
                    'ref' => 'ref',
                    'pullRequest' => 'pullRequest',
                ],
            ],
            [
                [
                    'owner' => 'owner',
                    'repository' => 'repository',
                    'provider' => 'invalid-provider',
                    'fileName' => 'fileName',
                    'projectRoot' => 'projectRoot',
                    'tag' => 'tag',
                    'commit' => 'commit',
                    'parent' => ['parent'],
                    'ref' => 'ref',
                    'pullRequest' => 'pullRequest',
                ],
            ],
            [
                [
                    'owner' => 'owner',
                    'repository' => 'repository',
                    'provider' => Provider::GITHUB->value,
                    'projectRoot' => 'projectRoot',
                    'tag' => 'tag',
                    'commit' => 'commit',
                    'parent' => ['parent'],
                    'ref' => 'ref',
                    'pullRequest' => 'pullRequest',
                ],
            ],
            [
                [
                    'owner' => 'owner',
                    'repository' => 'repository',
                    'provider' => Provider::GITHUB->value,
                    'fileName' => 'fileName',
                    'tag' => 'tag',
                    'commit' => 'commit',
                    'parent' => ['parent'],
                    'ref' => 'ref',
                    'pullRequest' => 'pullRequest',
                ],
            ],
            [
                [
                    'owner' => 'owner',
                    'repository' => 'repository',
                    'provider' => Provider::GITHUB->value,
                    'fileName' => 'fileName',
                    'projectRoot' => 'projectRoot',
                    'commit' => 'commit',
                    'parent' => ['parent'],
                    'ref' => 'ref',
                    'pullRequest' => 'pullRequest',
                ],
            ],
            [
                [
                    'owner' => 'owner',
                    'repository' => 'repository',
                    'provider' => Provider::GITHUB->value,
                    'fileName' => 'fileName',
                    'projectRoot' => 'projectRoot',
                    'tag' => 'tag',
                    'parent' => ['parent'],
                    'ref' => 'ref',
                    'pullRequest' => 'pullRequest',
                ],
            ],
            [
                [
                    'owner' => 'owner',
                    'repository' => 'repository',
                    'provider' => Provider::GITHUB->value,
                    'fileName' => 'fileName',
                    'projectRoot' => 'projectRoot',
                    'tag' => 'tag',
                    'commit' => 'commit',
                    'ref' => 'ref',
                    'pullRequest' => 'pullRequest',
                ],
            ],
            [
                [
                    'owner' => 'owner',
                    'repository' => 'repository',
                    'provider' => Provider::GITHUB->value,
                    'fileName' => 'fileName',
                    'projectRoot' => 'projectRoot',
                    'tag' => 'tag',
                    'commit' => 'commit',
                    'parent' => ['parent'],
                    'pullRequest' => 'pullRequest',
                ]
            ],
        ];
    }
}
