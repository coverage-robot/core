<?php

declare(strict_types=1);

namespace App\Tests\Model;

use App\Model\SigningParameters;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\TestCase;

final class SigningParametersTest extends TestCase
{
    public function testUsingGettersReturnsProperties(): void
    {
        $parameters = new SigningParameters(
            owner: 'owner',
            repository: 'repository',
            provider: Provider::GITHUB,
            fileName: 'fileName',
            projectRoot: 'projectRoot',
            tag: 'tag',
            commit: 'commit',
            parent: ['parent'],
            ref: 'ref',
            pullRequest: 'pullRequest',
            baseRef: 'baseRef',
            baseCommit: 'baseCommit'
        );

        $this->assertSame('owner', $parameters->getOwner());
        $this->assertSame('repository', $parameters->getRepository());
        $this->assertSame(Provider::GITHUB, $parameters->getProvider());
        $this->assertSame('fileName', $parameters->getFileName());
        $this->assertSame('projectRoot', $parameters->getProjectRoot());
        $this->assertSame('tag', $parameters->getTag());
        $this->assertSame('commit', $parameters->getCommit());
        $this->assertSame(['parent'], $parameters->getParent());
        $this->assertSame('ref', $parameters->getRef());
        $this->assertSame('pullRequest', $parameters->getPullRequest());
        $this->assertSame('baseCommit', $parameters->getBaseCommit());
        $this->assertSame('baseRef', $parameters->getBaseRef());
    }
}
