<?php

declare(strict_types=1);

namespace App\Tests\Event;

use App\Client\DynamoDbClientInterface;
use App\Event\CoverageFinalisedEventProcessor;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Model\CoverageFinalised;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class CoverageFinalisedEventProcessorTest extends TestCase
{
    public function testValidCoverageEventProcess(): void
    {
        $mockDynamoDbClient = $this->createMock(DynamoDbClientInterface::class);
        $mockDynamoDbClient->expects($this->once())
            ->method('setCoveragePercentage');

        $eventProcessor = new CoverageFinalisedEventProcessor(
            new NullLogger(),
            $mockDynamoDbClient
        );

        $eventProcessor->process(
            new CoverageFinalised(
                provider: Provider::GITHUB,
                projectId: '0192c0b2-a63e-7c29-8636-beb65b9097ee',
                owner: 'mock-owner',
                repository: 'mock-repository',
                ref: 'main',
                commit: 'mock-commit',
                coveragePercentage: 99.0,
                pullRequest: 12,
                baseRef: 'main',
                baseCommit: 'mock-main-commit'
            )
        );
    }
}
