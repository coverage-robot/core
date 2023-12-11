<?php

namespace App\Tests\Handler;

use App\Handler\EventHandler;
use App\Service\Publisher\MessagePublisherService;
use Bref\Context\Context;
use Bref\Event\Sqs\SqsEvent;
use DateTimeInterface;
use Monolog\DateTimeImmutable;
use Packages\Message\PublishableMessage\PublishableMessageInterface;
use Packages\Message\PublishableMessage\PublishableMissingCoverageAnnotationMessage;
use Packages\Message\PublishableMessage\PublishablePullRequestMessage;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

class EventHandlerTest extends KernelTestCase
{
    public function testReceivingSingleMessageInSingleGroup(): void
    {
        $mockCoveragePublisherService = $this->createMock(MessagePublisherService::class);
        $mockCoveragePublisherService->expects($this->once())
            ->method('publish')
            ->with(
                self::callback(
                    function (PublishableMessageInterface $message) {
                        $this->assertInstanceOf(PublishablePullRequestMessage::class, $message);
                        return true;
                    }
                )
            )
            ->willReturn(true);

        $sqsEvent = new SqsEvent(
            [
                'Records' => [
                    [
                        'eventSource' => 'aws:sqs',
                        'messageId' => '1',
                        'body' => json_encode([
                            'type' => 'COLLECTION',
                            'messages' => [
                                0 => [
                                    'type' => 'PULL_REQUEST',
                                    'event' => [
                                        'type' => 'UPLOAD',
                                        'uploadId' => 'mock-uuid',
                                        'provider' => 'github',
                                        'owner' => 'mock-owner',
                                        'repository' => 'mock-repository',
                                        'ref' => 'main',
                                        'projectRoot' => 'mock-root',
                                        'pullRequest' => 1,
                                        'ingestTime' => '2023-09-18T01:15:37+00:00',
                                        'eventTime' => '2023-09-18T01:15:37+00:00',
                                        'commit' => 'mock-commit',
                                        'parent' => [],
                                        'tag' => [
                                            'name' => 'mock-tag',
                                            'commit' => 'mock-commit',
                                        ],
                                    ],
                                    'validUntil' => '2023-09-18T01:15:39+00:00',
                                    'messageGroup' => 'd047439957bad1d7f75c954f2fbe434c',
                                    'coveragePercentage' => 100.0,
                                    'diffCoveragePercentage' => 100.0,
                                    'successfulUploads' => 1,
                                    'pendingUploads' => 0,
                                    'tagCoverage' => [
                                        0 => [
                                            'tag' => [
                                                'name' => 'mock-tag',
                                                'commit' => 'mock-commit',
                                            ],
                                            'lines' => 1,
                                            'covered' => 1,
                                            'partial' => 0,
                                            'uncovered' => 0,
                                            'coverage' => 100,
                                        ],
                                    ],
                                    'leastCoveredDiffFiles' => [],
                                ],
                            ],
                            'event' => [
                                'type' => 'UPLOAD',
                                'uploadId' => 'mock-uuid',
                                'provider' => 'github',
                                'owner' => 'mock-owner',
                                'repository' => 'mock-repository',
                                'ref' => 'main',
                                'projectRoot' => 'mock-root',
                                'pullRequest' => 1,
                                'ingestTime' => '2023-09-18T01:15:37+00:00',
                                'eventTime' => '2023-09-18T01:15:37+00:00',
                                'commit' => 'mock-commit',
                                'parent' => [],
                                'tag' => [
                                    'name' => 'mock-tag',
                                    'commit' => 'mock-commit',
                                ],
                            ],
                            'validUntil' => '2023-09-18T01:15:39+00:00',
                            'messageGroup' => 'd047439957bad1d7f75c954f2fbe434c',
                        ]),
                        'attributes' => [
                            'ApproximateReceiveCount' => '1',
                            'SentTimestamp' => '1234',
                            'SequenceNumber' => '1',
                            'MessageGroupId' => '1',
                            'SenderId' => '987',
                            'MessageDeduplicationId' => '1',
                            'ApproximateFirstReceiveTimestamp' => '1234'
                        ]
                    ]
                ]
            ]
        );

        $eventHandler = new EventHandler(
            $mockCoveragePublisherService,
            $this->getContainer()->get(SerializerInterface::class)
        );

        $eventHandler->handleSqs($sqsEvent, Context::fake());
    }

    public function testReceivingMultipleMessageInSingleGroup(): void
    {
        $mockCoveragePublisherService = $this->createMock(MessagePublisherService::class);
        $mockCoveragePublisherService->expects($this->once())
            ->method('publish')
            ->with(
                self::callback(
                    function (PublishableMessageInterface $message) {
                        $this->assertInstanceOf(PublishablePullRequestMessage::class, $message);

                        // It should pick the latest of the two messages for the same message group
                        // (regardless of sequence)
                        $this->assertEquals(
                            DateTimeImmutable::createFromFormat(
                                DateTimeInterface::ATOM,
                                '2023-08-30T12:00:78+00:00'
                            ),
                            $message->getValidUntil()
                        );

                        return true;
                    }
                )
            )
            ->willReturn(true);

        $sqsEvent = new SqsEvent(
            [
                'Records' => [
                    [
                        'eventSource' => 'aws:sqs',
                        'messageId' => '1',
                        'body' => json_encode([
                            'type' => 'COLLECTION',
                            'messages' => [
                                0 => [
                                    'type' => 'PULL_REQUEST',
                                    'event' => [
                                        'type' => 'UPLOAD',
                                        'uploadId' => 'mock-uuid',
                                        'provider' => 'github',
                                        'owner' => 'mock-owner',
                                        'repository' => 'mock-repository',
                                        'ref' => 'main',
                                        'projectRoot' => 'mock-root',
                                        'pullRequest' => 1,
                                        'ingestTime' => '2023-09-18T01:28:36+00:00',
                                        'eventTime' => '2023-09-18T01:28:36+00:00',
                                        'commit' => 'mock-commit',
                                        'parent' => [],
                                        'tag' => [
                                            'name' => 'mock-tag',
                                            'commit' => 'mock-commit',
                                        ],
                                        'eventType' => 'UPLOAD',
                                    ],
                                    'validUntil' => '2023-08-30T12:00:00+00:00',
                                    'messageGroup' => 'd047439957bad1d7f75c954f2fbe434c',
                                    'coveragePercentage' => 100.0,
                                    'diffCoveragePercentage' => 100.0,
                                    'successfulUploads' => 1,
                                    'pendingUploads' => 0,
                                    'tagCoverage' => [
                                        0 => [
                                            'tag' => [
                                                'name' => 'mock-tag',
                                                'commit' => 'mock-commit',
                                            ],
                                            'lines' => 1,
                                            'covered' => 1,
                                            'partial' => 0,
                                            'uncovered' => 0,
                                            'coverage' => 100,
                                        ],
                                    ],
                                    'leastCoveredDiffFiles' => [],
                                ],
                            ],
                            'event' => [
                                'type' => 'UPLOAD',
                                'uploadId' => 'mock-uuid',
                                'provider' => 'github',
                                'owner' => 'mock-owner',
                                'repository' => 'mock-repository',
                                'ref' => 'main',
                                'projectRoot' => 'mock-root',
                                'pullRequest' => 1,
                                'ingestTime' => '2023-09-18T01:28:36+00:00',
                                'eventTime' => '2023-09-18T01:28:36+00:00',
                                'commit' => 'mock-commit',
                                'parent' => [],
                                'tag' => [
                                    'name' => 'mock-tag',
                                    'commit' => 'mock-commit',
                                ],
                                'eventType' => 'UPLOAD',
                            ],
                            'validUntil' => '2023-08-30T12:00:00+00:00',
                            'messageGroup' => 'd047439957bad1d7f75c954f2fbe434c',
                        ]),
                        'attributes' => [
                            'ApproximateReceiveCount' => '1',
                            'SentTimestamp' => '1234',
                            'SequenceNumber' => '2',
                            'MessageGroupId' => '1',
                            'SenderId' => '987',
                            'MessageDeduplicationId' => '1',
                            'ApproximateFirstReceiveTimestamp' => '1234'
                        ]
                    ],
                    [
                        'eventSource' => 'aws:sqs',
                        'messageId' => '1',
                        'body' => json_encode([
                            'type' => 'COLLECTION',
                            'messages' => [
                                0 => [
                                    'type' => 'PULL_REQUEST',
                                    'event' => [
                                        'type' => 'UPLOAD',
                                        'uploadId' => 'mock-uuid',
                                        'provider' => 'github',
                                        'owner' => 'mock-owner',
                                        'repository' => 'mock-repository',
                                        'ref' => 'main',
                                        'projectRoot' => 'mock-root',
                                        'pullRequest' => 1,
                                        'ingestTime' => '2023-09-18T01:34:28+00:00',
                                        'eventTime' => '2023-09-18T01:34:28+00:00',
                                        'commit' => 'mock-commit',
                                        'parent' => [],
                                        'tag' => [
                                            'name' => 'mock-tag',
                                            'commit' => 'mock-commit',
                                        ],
                                        'eventType' => 'UPLOAD',
                                    ],
                                    'validUntil' => '2023-08-30T12:01:18+00:00',
                                    'messageGroup' => 'd047439957bad1d7f75c954f2fbe434c',
                                    'coveragePercentage' => 50.0,
                                    'diffCoveragePercentage' => 100.0,
                                    'successfulUploads' => 2,
                                    'pendingUploads' => 0,
                                    'tagCoverage' => [
                                        0 => [
                                            'tag' => [
                                                'name' => 'mock-tag',
                                                'commit' => 'mock-commit',
                                            ],
                                            'lines' => 1,
                                            'covered' => 1,
                                            'partial' => 0,
                                            'uncovered' => 0,
                                            'coverage' => 100,
                                        ],
                                        1 => [
                                            'tag' => [
                                                'name' => 'mock-tag-2',
                                                'commit' => 'mock-commit',
                                            ],
                                            'lines' => 2,
                                            'covered' => 1,
                                            'partial' => 0,
                                            'uncovered' => 1,
                                            'coverage' => 50,
                                        ],
                                    ],
                                    'leastCoveredDiffFiles' => [],
                                ],
                            ],
                            'event' => [
                                'type' => 'UPLOAD',
                                'uploadId' => 'mock-uuid',
                                'provider' => 'github',
                                'owner' => 'mock-owner',
                                'repository' => 'mock-repository',
                                'ref' => 'main',
                                'projectRoot' => 'mock-root',
                                'pullRequest' => 1,
                                'ingestTime' => '2023-09-18T01:34:28+00:00',
                                'eventTime' => '2023-09-18T01:34:28+00:00',
                                'commit' => 'mock-commit',
                                'parent' => [],
                                'tag' => [
                                    'name' => 'mock-tag',
                                    'commit' => 'mock-commit',
                                ],
                                'eventType' => 'UPLOAD',
                            ],
                            'validUntil' => '2023-08-30T12:01:18+00:00',
                            'messageGroup' => 'd047439957bad1d7f75c954f2fbe434c',
                        ]),
                        'attributes' => [
                            'ApproximateReceiveCount' => '1',
                            'SentTimestamp' => '1234',
                            'SequenceNumber' => '1',
                            'MessageGroupId' => '1',
                            'SenderId' => '987',
                            'MessageDeduplicationId' => '1',
                            'ApproximateFirstReceiveTimestamp' => '1234'
                        ]
                    ]
                ]
            ]
        );

        $eventHandler = new EventHandler(
            $mockCoveragePublisherService,
            $this->getContainer()->get(SerializerInterface::class)
        );

        $eventHandler->handleSqs($sqsEvent, Context::fake());
    }

    public function testReceivingMultipleMessageAndMultipleGroups(): void
    {
        $mockCoveragePublisherService = $this->createMock(MessagePublisherService::class);
        $publishMatcher = $this->exactly(2);
        $mockCoveragePublisherService->expects($publishMatcher)
            ->method('publish')
            ->with(
                self::callback(
                    function (PublishableMessageInterface $message) use ($publishMatcher) {
                        switch ($publishMatcher->numberOfInvocations()) {
                            case 1:
                                $this->assertInstanceOf(
                                    PublishablePullRequestMessage::class,
                                    $message
                                );

                                // It should pick the latest of the two messages for the same message group
                                // (regardless of sequence)
                                $this->assertEquals(
                                    DateTimeImmutable::createFromFormat(
                                        DateTimeInterface::ATOM,
                                        '2023-08-30T12:00:78+00:00'
                                    ),
                                    $message->getValidUntil()
                                );
                                break;
                            case 2:
                                $this->assertInstanceOf(
                                    PublishableMissingCoverageAnnotationMessage::class,
                                    $message
                                );
                                $this->assertEquals(
                                    DateTimeImmutable::createFromFormat(
                                        DateTimeInterface::ATOM,
                                        '2023-08-30T12:01:00+00:00'
                                    ),
                                    $message->getValidUntil()
                                );
                                break;
                        }

                        return true;
                    }
                )
            )
            ->willReturn(true);

        $sqsEvent = new SqsEvent(
            [
                'Records' => [
                    [
                        'eventSource' => 'aws:sqs',
                        'messageId' => '1',
                        'body' => json_encode([
                            'type' => 'COLLECTION',
                            'messages' => [
                                0 => [
                                    'type' => 'PULL_REQUEST',
                                    'event' => [
                                        'type' => 'UPLOAD',
                                        'uploadId' => 'mock-uuid',
                                        'provider' => 'github',
                                        'owner' => 'mock-owner',
                                        'repository' => 'mock-repository',
                                        'ref' => 'main',
                                        'projectRoot' => 'mock-root',
                                        'pullRequest' => 1,
                                        'ingestTime' => '2023-09-18T01:29:37+00:00',
                                        'eventTime' => '2023-09-18T01:29:37+00:00',
                                        'commit' => 'mock-commit',
                                        'parent' => [],
                                        'tag' => [
                                            'name' => 'mock-tag',
                                            'commit' => 'mock-commit',
                                        ],
                                        'eventType' => 'UPLOAD',
                                    ],
                                    'validUntil' => '2023-08-30T12:00:00+00:00',
                                    'messageGroup' => 'd047439957bad1d7f75c954f2fbe434c',
                                    'coveragePercentage' => 100.0,
                                    'diffCoveragePercentage' => 100.0,
                                    'successfulUploads' => 1,
                                    'pendingUploads' => 0,
                                    'tagCoverage' => [
                                        0 => [
                                            'tag' => [
                                                'name' => 'mock-tag',
                                                'commit' => 'mock-commit',
                                            ],
                                            'lines' => 1,
                                            'covered' => 1,
                                            'partial' => 0,
                                            'uncovered' => 0,
                                            'coverage' => 100,
                                        ],
                                    ],
                                    'leastCoveredDiffFiles' => [],
                                ],
                            ],
                            'event' => [
                                'type' => 'UPLOAD',
                                'uploadId' => 'mock-uuid',
                                'provider' => 'github',
                                'owner' => 'mock-owner',
                                'repository' => 'mock-repository',
                                'ref' => 'main',
                                'projectRoot' => 'mock-root',
                                'pullRequest' => 1,
                                'ingestTime' => '2023-09-18T01:29:37+00:00',
                                'eventTime' => '2023-09-18T01:29:37+00:00',
                                'commit' => 'mock-commit',
                                'parent' => [
                                ],
                                'tag' => [
                                    'name' => 'mock-tag',
                                    'commit' => 'mock-commit',
                                ],
                                'eventType' => 'UPLOAD',
                            ],
                            'validUntil' => '2023-08-30T12:00:00+00:00',
                            'messageGroup' => 'd047439957bad1d7f75c954f2fbe434c',
                        ]),
                        'attributes' => [
                            'ApproximateReceiveCount' => '1',
                            'SentTimestamp' => '1234',
                            'SequenceNumber' => '1',
                            'MessageGroupId' => '1',
                            'SenderId' => '987',
                            'MessageDeduplicationId' => '1',
                            'ApproximateFirstReceiveTimestamp' => '1234'
                        ]
                    ],
                    [
                        'eventSource' => 'aws:sqs',
                        'messageId' => '2',
                        'body' => json_encode([
                            'type' => 'COLLECTION',
                            'messages' => [
                                0 => [
                                    'type' => 'PULL_REQUEST',
                                    'event' => [
                                        'type' => 'UPLOAD',
                                        'uploadId' => 'mock-uuid',
                                        'provider' => 'github',
                                        'owner' => 'mock-owner',
                                        'repository' => 'mock-repository',
                                        'ref' => 'main',
                                        'projectRoot' => 'mock-root',
                                        'pullRequest' => 1,
                                        'ingestTime' => '2023-09-18T01:30:39+00:00',
                                        'eventTime' => '2023-09-18T01:30:39+00:00',
                                        'commit' => 'mock-commit',
                                        'parent' => [
                                        ],
                                        'tag' => [
                                            'name' => 'mock-tag',
                                            'commit' => 'mock-commit',
                                        ],
                                        'eventType' => 'UPLOAD',
                                    ],
                                    'validUntil' => '2023-08-30T12:01:18+00:00',
                                    'messageGroup' => 'd047439957bad1d7f75c954f2fbe434c',
                                    'coveragePercentage' => 50.0,
                                    'diffCoveragePercentage' => 100.0,
                                    'successfulUploads' => 2,
                                    'pendingUploads' => 0,
                                    'tagCoverage' => [
                                        0 => [
                                            'tag' => [
                                                'name' => 'mock-tag',
                                                'commit' => 'mock-commit',
                                            ],
                                            'lines' => 1,
                                            'covered' => 1,
                                            'partial' => 0,
                                            'uncovered' => 0,
                                            'coverage' => 100,
                                        ],
                                        1 => [
                                            'tag' => [
                                                'name' => 'mock-tag-2',
                                                'commit' => 'mock-commit',
                                            ],
                                            'lines' => 2,
                                            'covered' => 1,
                                            'partial' => 0,
                                            'uncovered' => 1,
                                            'coverage' => 50,
                                        ],
                                    ],
                                    'leastCoveredDiffFiles' => [
                                    ],
                                ],
                            ],
                            'event' => [
                                'type' => 'UPLOAD',
                                'uploadId' => 'mock-uuid',
                                'provider' => 'github',
                                'owner' => 'mock-owner',
                                'repository' => 'mock-repository',
                                'ref' => 'main',
                                'projectRoot' => 'mock-root',
                                'pullRequest' => 1,
                                'ingestTime' => '2023-09-18T01:30:39+00:00',
                                'eventTime' => '2023-09-18T01:30:39+00:00',
                                'commit' => 'mock-commit',
                                'parent' => [
                                ],
                                'tag' => [
                                    'name' => 'mock-tag',
                                    'commit' => 'mock-commit',
                                ],
                                'eventType' => 'UPLOAD',
                            ],
                            'validUntil' => '2023-08-30T12:01:18+00:00',
                            'messageGroup' => 'd047439957bad1d7f75c954f2fbe434c',
                        ]),
                        'attributes' => [
                            'ApproximateReceiveCount' => '1',
                            'SentTimestamp' => '1234',
                            'SequenceNumber' => '2',
                            'MessageGroupId' => '1',
                            'SenderId' => '987',
                            'MessageDeduplicationId' => '1',
                            'ApproximateFirstReceiveTimestamp' => '1234'
                        ]
                    ],
                    [
                        'eventSource' => 'aws:sqs',
                        'messageId' => '3',
                        'body' => json_encode([
                            'type' => 'COLLECTION',
                            'messages' => [
                                0 => [
                                    'type' => 'MISSING_COVERAGE_ANNOTATION',
                                    'event' => [
                                        'type' => 'UPLOAD',
                                        'uploadId' => 'mock-uuid',
                                        'provider' => 'github',
                                        'owner' => 'mock-owner',
                                        'repository' => 'mock-repository',
                                        'ref' => 'main',
                                        'projectRoot' => 'mock-root',
                                        'pullRequest' => 1,
                                        'ingestTime' => '2023-09-18T01:31:35+00:00',
                                        'eventTime' => '2023-09-18T01:31:35+00:00',
                                        'commit' => 'mock-commit',
                                        'parent' => [
                                        ],
                                        'tag' => [
                                            'name' => 'mock-tag',
                                            'commit' => 'mock-commit',
                                        ],
                                        'eventType' => 'UPLOAD',
                                    ],
                                    'fileName' => 'mock-file.php',
                                    'startingOnMethod' => true,
                                    'startLineNumber' => 1,
                                    'endLineNumber' => 5,
                                    'validUntil' => '2023-08-30T12:01:00+00:00',
                                    'messageGroup' => '6bfe4fbaf34561246b97f9e8a8c66082',
                                ],
                            ],
                            'event' => [
                                'type' => 'UPLOAD',
                                'uploadId' => 'mock-uuid',
                                'provider' => 'github',
                                'owner' => 'mock-owner',
                                'repository' => 'mock-repository',
                                'ref' => 'main',
                                'projectRoot' => 'mock-root',
                                'pullRequest' => 1,
                                'ingestTime' => '2023-09-18T01:31:35+00:00',
                                'eventTime' => '2023-09-18T01:31:35+00:00',
                                'commit' => 'mock-commit',
                                'parent' => [
                                ],
                                'tag' => [
                                    'name' => 'mock-tag',
                                    'commit' => 'mock-commit',
                                ],
                                'eventType' => 'UPLOAD',
                            ],
                            'validUntil' => '2023-08-30T12:01:00+00:00',
                            'messageGroup' => 'd047439957bad1d7f75c954f2fbe434c',
                        ]),
                        'attributes' => [
                            'ApproximateReceiveCount' => '1',
                            'SentTimestamp' => '0',
                            'SequenceNumber' => '1',
                            'MessageGroupId' => '2',
                            'SenderId' => '987',
                            'MessageDeduplicationId' => '1',
                            'ApproximateFirstReceiveTimestamp' => '0'
                        ]
                    ]
                ]
            ]
        );

        $eventHandler = new EventHandler(
            $mockCoveragePublisherService,
            $this->getContainer()->get(SerializerInterface::class)
        );

        $eventHandler->handleSqs($sqsEvent, Context::fake());
    }
}
