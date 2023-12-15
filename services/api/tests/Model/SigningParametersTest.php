<?php

namespace App\Tests\Model;

use App\Model\SigningParameters;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\TestCase;

class SigningParametersTest extends TestCase
{
    public function testUsingGettersReturnsProperties(): void
    {
        $parameters = new SigningParameters(
            'owner',
            'repository',
            Provider::GITHUB,
            'fileName',
            'projectRoot',
            'tag',
            'commit',
            ['parent'],
            'ref',
            'pullRequest',
            'baseRef',
            'baseCommit'
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
        $this->assertEquals('baseCommit', $parameters->getBaseCommit());
        $this->assertEquals('baseRef', $parameters->getBaseRef());
    }
}
