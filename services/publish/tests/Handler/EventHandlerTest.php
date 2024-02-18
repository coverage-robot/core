<?php

namespace App\Tests\Handler;

use App\Handler\EventHandler;
use App\Service\MessagePublisherService;
use App\Service\MessagePublisherServiceInterface;
use App\Service\Publisher\PublisherServiceInterface;
use Bref\Context\Context;
use Bref\Event\Sqs\SqsEvent;
use DateTimeInterface;
use Monolog\DateTimeImmutable;
use Packages\Contracts\Provider\Provider;
use Packages\Contracts\Tag\Tag;
use Packages\Event\Model\Upload;
use Packages\Message\PublishableMessage\PublishableMessageCollection;
use Packages\Message\PublishableMessage\PublishableMessageInterface;
use Packages\Message\PublishableMessage\PublishableMissingCoverageLineCommentMessage;
use Packages\Message\PublishableMessage\PublishablePullRequestMessage;
use Packages\Message\Service\MessageValidationService;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class EventHandlerTest extends KernelTestCase
{
    public function testReceivingSingleMessageInSingleGroup(): void
    {
        $mockCoveragePublisherService = $this->createMock(MessagePublisherServiceInterface::class);
        $mockCoveragePublisherService->expects($this->once())
            ->method('publish')
            ->with(
                self::callback(
                    function (PublishableMessageInterface $message): bool {
                        $this->assertInstanceOf(PublishablePullRequestMessage::class, $message);
                        return true;
                    }
                )
            )
            ->willReturn(true);

        /** @var SerializerInterface $serializer */
        $serializer = $this->getContainer()
            ->get(SerializerInterface::class);

        $event = new Upload(
            uploadId: 'mock-uuid',
            provider: Provider::GITHUB,
            owner: 'mock-owner',
            repository: 'mock-repository',
            commit: 'mock-commit',
            parent: [],
            ref: 'main',
            projectRoot: 'mock-root',
            tag: new Tag(
                name: 'mock-tag',
                commit: 'mock-commit'
            ),
            pullRequest: 1,
            eventTime: new DateTimeImmutable('2023-09-18 01:15:37')
        );

        $collection = $serializer->serialize(
            new PublishableMessageCollection(
                $event,
                [
                    new PublishablePullRequestMessage(
                        event: $event,
                        coveragePercentage: 100.0,
                        diffCoveragePercentage: 100.0,
                        successfulUploads: 1,
                        tagCoverage: [
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
                        leastCoveredDiffFiles: [],
                        validUntil: new DateTimeImmutable('2023-08-30 12:00:78'),
                    )
                ]
            ),
            'json'
        );

        $sqsEvent = new SqsEvent(
            [
                'Records' => [
                    [
                        'eventSource' => 'aws:sqs',
                        'messageId' => '1',
                        'body' => $collection,
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
            $serializer,
            $this->getContainer()
                ->get(MessageValidationService::class),
            new NullLogger()
        );

        $eventHandler->handleSqs($sqsEvent, Context::fake());
    }

    public function testReceivingMultipleMessageInSingleGroup(): void
    {
        $mockCoveragePublisherService = $this->createMock(MessagePublisherServiceInterface::class);
        $mockCoveragePublisherService->expects($this->once())
            ->method('publish')
            ->with(
                self::callback(
                    function (PublishableMessageInterface $message): bool {
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

        /** @var SerializerInterface $serializer */
        $serializer = $this->getContainer()
            ->get(SerializerInterface::class);

        $event = new Upload(
            uploadId: 'mock-uuid',
            provider: Provider::GITHUB,
            owner: 'mock-owner',
            repository: 'mock-repository',
            commit: 'mock-commit',
            parent: [],
            ref: 'main',
            projectRoot: 'mock-root',
            tag: new Tag(
                name: 'mock-tag',
                commit: 'mock-commit'
            ),
            pullRequest: 1,
            eventTime: DateTimeImmutable::createFromFormat(
                DateTimeInterface::ATOM,
                '2023-08-30T12:00:78+00:00'
            )
        );

        $collection = $serializer->serialize(
            new PublishableMessageCollection(
                $event,
                [
                    new PublishablePullRequestMessage(
                        event: $event,
                        coveragePercentage: 100.0,
                        diffCoveragePercentage: 100.0,
                        successfulUploads: 1,
                        tagCoverage: [
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
                        leastCoveredDiffFiles: [],
                        validUntil: DateTimeImmutable::createFromFormat(
                            DateTimeInterface::ATOM,
                            '2023-08-30T12:00:78+00:00'
                        ),
                    )
                ],
            ),
            'json'
        );

        $sqsEvent = new SqsEvent(
            [
                'Records' => [
                    [
                        'eventSource' => 'aws:sqs',
                        'messageId' => '1',
                        'body' => $collection,
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
                        'body' => $collection,
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
            $serializer,
            $this->getContainer()
                ->get(MessageValidationService::class),
            new NullLogger()
        );

        $eventHandler->handleSqs($sqsEvent, Context::fake());
    }
}
