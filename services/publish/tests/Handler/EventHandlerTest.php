<?php

namespace App\Tests\Handler;

use App\Handler\EventHandler;
use App\Service\Publisher\MessagePublisherService;
use Bref\Context\Context;
use Bref\Event\Sqs\SqsEvent;
use DateTimeInterface;
use Monolog\DateTimeImmutable;
use Packages\Models\Enum\LineState;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\PublishableMessage\PublishableCheckAnnotationMessage;
use Packages\Models\Model\PublishableMessage\PublishableMessageCollection;
use Packages\Models\Model\PublishableMessage\PublishableMessageInterface;
use Packages\Models\Model\PublishableMessage\PublishablePullRequestMessage;
use Packages\Models\Model\Tag;
use Packages\Models\Model\Event\Upload;
use PHPUnit\Framework\TestCase;

class EventHandlerTest extends TestCase
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


        $upload = new Upload(
            'mock-uuid',
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-commit',
            [],
            'master',
            1,
            new Tag('mock-tag', 'mock-commit'),
        );

        $sqsEvent = new SqsEvent(
            [
                'Records' => [
                    [
                        'eventSource' => 'aws:sqs',
                        'messageId' => '1',
                        'body' => json_encode(
                            new PublishableMessageCollection(
                                $upload,
                                [
                                    new PublishablePullRequestMessage(
                                        $upload,
                                        100,
                                        100,
                                        1,
                                        0,
                                        [
                                            [
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
                                        [],
                                        new DateTimeImmutable('2023-08-30T12:00:00+00:00')
                                    )
                                ]
                            )
                        ),
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

        $eventHandler = new EventHandler($mockCoveragePublisherService);

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

        $upload = new Upload(
            'mock-uuid',
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-commit',
            [],
            'master',
            1,
            new Tag('mock-tag', 'mock-commit'),
        );

        $sqsEvent = new SqsEvent(
            [
                'Records' => [
                    [
                        'eventSource' => 'aws:sqs',
                        'messageId' => '1',
                        'body' => json_encode(
                            new PublishableMessageCollection(
                                $upload,
                                [
                                    new PublishablePullRequestMessage(
                                        $upload,
                                        100,
                                        100,
                                        1,
                                        0,
                                        [
                                            [
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
                                        [],
                                        DateTimeImmutable::createFromFormat(
                                            DateTimeInterface::ATOM,
                                            '2023-08-30T12:00:00+00:00'
                                        )
                                    )
                                ]
                            )
                        ),
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
                        'body' => json_encode(
                            new PublishableMessageCollection(
                                $upload,
                                [
                                    new PublishablePullRequestMessage(
                                        $upload,
                                        50,
                                        100,
                                        2,
                                        0,
                                        [
                                            [
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
                                            [
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
                                        [],
                                        DateTimeImmutable::createFromFormat(
                                            DateTimeInterface::ATOM,
                                            '2023-08-30T12:00:78+00:00'
                                        )
                                    )
                                ]
                            )
                        ),
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

        $eventHandler = new EventHandler($mockCoveragePublisherService);

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
                                    PublishableCheckAnnotationMessage::class,
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

        $upload = new Upload(
            'mock-uuid',
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-commit',
            [],
            'master',
            1,
            new Tag('mock-tag', 'mock-commit'),
        );

        $sqsEvent = new SqsEvent(
            [
                'Records' => [
                    [
                        'eventSource' => 'aws:sqs',
                        'messageId' => '1',
                        'body' => json_encode(
                            new PublishableMessageCollection(
                                $upload,
                                [
                                    new PublishablePullRequestMessage(
                                        $upload,
                                        100,
                                        100,
                                        1,
                                        0,
                                        [
                                            [
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
                                        [],
                                        DateTimeImmutable::createFromFormat(
                                            DateTimeInterface::ATOM,
                                            '2023-08-30T12:00:00+00:00'
                                        )
                                    )
                                ]
                            )
                        ),
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
                        'body' => json_encode(
                            new PublishableMessageCollection(
                                $upload,
                                [
                                    new PublishablePullRequestMessage(
                                        $upload,
                                        50,
                                        100,
                                        2,
                                        0,
                                        [
                                            [
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
                                            [
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
                                        [],
                                        DateTimeImmutable::createFromFormat(
                                            DateTimeInterface::ATOM,
                                            '2023-08-30T12:00:78+00:00'
                                        )
                                    )
                                ]
                            )
                        ),
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
                        'body' => json_encode(
                            new PublishableMessageCollection(
                                $upload,
                                [
                                    new PublishableCheckAnnotationMessage(
                                        $upload,
                                        'mock-file.php',
                                        1,
                                        LineState::UNCOVERED,
                                        DateTimeImmutable::createFromFormat(
                                            DateTimeInterface::ATOM,
                                            '2023-08-30T12:01:00+00:00'
                                        )
                                    )
                                ]
                            )
                        ),
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

        $eventHandler = new EventHandler($mockCoveragePublisherService);

        $eventHandler->handleSqs($sqsEvent, Context::fake());
    }
}
