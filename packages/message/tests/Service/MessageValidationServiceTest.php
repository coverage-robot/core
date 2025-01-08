<?php

declare(strict_types=1);

namespace Packages\Message\Tests\Service;

use Packages\Contracts\Provider\Provider;
use Packages\Contracts\PublishableMessage\PublishableMessageInterface;
use Packages\Contracts\Tag\Tag;
use Packages\Event\Model\Upload;
use Packages\Message\Exception\InvalidMessageException;
use Packages\Message\PublishableMessage\PublishablePullRequestMessage;
use Packages\Message\Service\MessageValidationService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class MessageValidationServiceTest extends TestCase
{
    #[DataProvider('messageDataProvider')]
    public function testValidatingMessages(PublishableMessageInterface $message, bool $isValid): void
    {
        $messageValidatorService = new MessageValidationService(
            Validation::createValidatorBuilder()
                ->enableAttributeMapping()
                ->getValidator()
        );

        if (!$isValid) {
            $this->expectException(InvalidMessageException::class);
        }

        $valid = $messageValidatorService->validate($message);

        if ($isValid) {
            $this->assertTrue($valid);
        }
    }

    public static function messageDataProvider(): \Iterator
    {
        yield [
            new PublishablePullRequestMessage(
                event: new Upload(
                    uploadId: 'mock-uuid',
                    provider: Provider::GITHUB,
                    projectId: 'mock-project-id',
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    commit: 'mock-commit',
                    parent: [],
                    ref: 'mock-ref',
                    projectRoot: '',
                    tag: new Tag('1', 'mock-commit', [11]),
                    pullRequest: null,
                    baseCommit: null,
                    baseRef: null
                ),
                coveragePercentage: 99,
                diffCoveragePercentage: null,
                diffUncoveredLines: 10,
                successfulUploads: 1,
                tagCoverage: [],
                leastCoveredDiffFiles: [],
                uncoveredLinesChange: 0,
            ),
            true
        ];

        yield [
            new PublishablePullRequestMessage(
                event: new Upload(
                    uploadId: 'mock-uuid',
                    provider: Provider::GITHUB,
                    projectId: 'mock-project-id',
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    commit: 'mock-commit',
                    parent: [],
                    ref: 'mock-ref',
                    projectRoot: '',
                    tag: new Tag('1', 'mock-commit', [12]),
                    pullRequest: null,
                    baseCommit: null,
                    baseRef: null
                ),
                coveragePercentage: -1,
                diffCoveragePercentage: null,
                diffUncoveredLines: 10,
                successfulUploads: 1,
                tagCoverage: [],
                leastCoveredDiffFiles: [],
                uncoveredLinesChange: 0,
            ),
            false
        ];

        yield [
            new PublishablePullRequestMessage(
                event: new Upload(
                    uploadId: 'mock-uuid',
                    provider: Provider::GITHUB,
                    projectId: 'mock-project-id',
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    commit: 'mock-commit',
                    parent: [],
                    ref: 'mock-ref',
                    projectRoot: '',
                    tag: new Tag('1', 'mock-commit', [13]),
                    pullRequest: null,
                    baseCommit: null,
                    baseRef: null
                ),
                coveragePercentage: 99,
                diffCoveragePercentage: null,
                diffUncoveredLines: 10,
                successfulUploads: -1,
                tagCoverage: [],
                leastCoveredDiffFiles: [],
                uncoveredLinesChange: 0,
            ),
            false
        ];

        yield [
            new PublishablePullRequestMessage(
                event: new Upload(
                    uploadId: 'mock-uuid',
                    provider: Provider::GITHUB,
                    projectId: 'mock-project-id',
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    commit: 'mock-commit',
                    parent: [],
                    ref: 'mock-ref',
                    projectRoot: '',
                    tag: new Tag('1', 'mock-commit', [14]),
                    pullRequest: null,
                    baseCommit: null,
                    baseRef: null
                ),
                coveragePercentage: 100,
                diffCoveragePercentage: -1,
                diffUncoveredLines: 10,
                successfulUploads: 1,
                tagCoverage: [],
                leastCoveredDiffFiles: [],
                uncoveredLinesChange: 0,
            ),
            false
        ];

        yield [
            new PublishablePullRequestMessage(
                event: new Upload(
                    uploadId: 'mock-uuid',
                    provider: Provider::GITHUB,
                    projectId: 'mock-project-id',
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    commit: 'mock-commit',
                    parent: [],
                    ref: 'mock-ref',
                    projectRoot: '',
                    tag: new Tag('1', 'mock-commit', [15]),
                    pullRequest: null,
                    baseCommit: null,
                    baseRef: null
                ),
                coveragePercentage: 200,
                diffCoveragePercentage: null,
                diffUncoveredLines: 10,
                successfulUploads: 1,
                tagCoverage: [],
                leastCoveredDiffFiles: [],
                uncoveredLinesChange: 0,
            ),
            false
        ];

        yield [
            new PublishablePullRequestMessage(
                event: new Upload(
                    uploadId: 'mock-uuid',
                    provider: Provider::GITHUB,
                    projectId: 'mock-project-id',
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    commit: 'mock-commit',
                    parent: [],
                    ref: 'mock-ref',
                    projectRoot: '',
                    tag: new Tag('1', 'mock-commit', [16]),
                    pullRequest: null,
                    baseCommit: null,
                    baseRef: null
                ),
                coveragePercentage: 100,
                diffCoveragePercentage: 200,
                diffUncoveredLines: 10,
                successfulUploads: 1,
                tagCoverage: [],
                leastCoveredDiffFiles: [],
                uncoveredLinesChange: 0,
            ),
            false
        ];
    }
}
