<?php

namespace App\Tests\Handler;

use App\Client\EventBridgeEventClient;
use App\Exception\DeletionException;
use App\Exception\PersistException;
use App\Exception\RetrievalException;
use App\Handler\EventHandler;
use App\Service\CoverageFileParserService;
use App\Service\CoverageFilePersistService;
use App\Service\CoverageFileRetrievalService;
use AsyncAws\Core\Stream\ResultStream;
use AsyncAws\S3\Result\GetObjectOutput;
use Bref\Context\Context;
use Bref\Event\InvalidLambdaEvent;
use Bref\Event\S3\S3Event;
use Exception;
use Packages\Models\Enum\EventBus\CoverageEvent;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Event\Upload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

class EventHandlerTest extends KernelTestCase
{
    /**
     * @throws Exception
     * @throws InvalidLambdaEvent
     */
    #[DataProvider('validS3EventDataProvider')]
    public function testSuccessfullyHandleS3(S3Event $event, array $coverageFiles, array $expectedOutputKeys): void
    {
        $mockCoverageFileRetrievalService = $this->createMock(CoverageFileRetrievalService::class);
        $mockCoverageFileRetrievalService->expects($this->exactly(count($event->getRecords())))
            ->method('ingestFromS3')
            ->willReturnOnConsecutiveCalls(
                ...array_map(
                    [$this, 'getMockS3ObjectResponse'],
                    $coverageFiles
                )
            );

        $mockCoverageFileRetrievalService->expects($this->exactly(count($expectedOutputKeys)))
            ->method('deleteFromS3')
            ->willReturn(true);

        $mockCoverageFilePersistService = $this->createMock(CoverageFilePersistService::class);
        $mockCoverageFilePersistService->expects($this->exactly(count($expectedOutputKeys)))
            ->method('persist')
            ->with(
                self::callback(
                    static fn(Upload $upload) => $upload->getuploadId() === 'mock-uuid' &&
                        $upload->getCommit() === '1' &&
                        $upload->getParent() === ['2']
                ),
            )
            ->willReturn(true);

        $mockEventBridgeEventService = $this->createMock(EventBridgeEventClient::class);
        $mockEventBridgeEventService
            ->expects($this->exactly(count($coverageFiles) - count($expectedOutputKeys)))
            ->method('publishEvent')
            ->with(CoverageEvent::INGEST_FAILURE);

        $handler = new EventHandler(
            $this->getContainer()->get(SerializerInterface::class),
            $mockCoverageFileRetrievalService,
            $this->getContainer()->get(CoverageFileParserService::class),
            $mockCoverageFilePersistService,
            $mockEventBridgeEventService,
            new NullLogger()
        );

        $handler->handleS3($event, Context::fake());
    }

    #[DataProvider('validS3EventDataProvider')]
    public function testHandleS3FailsToRetrieve(S3Event $event): void
    {
        $mockCoverageFileRetrievalService = $this->createMock(CoverageFileRetrievalService::class);
        $mockCoverageFileRetrievalService->method('ingestFromS3')
            ->willThrowException(RetrievalException::from(new Exception('Failed to retrieve')));

        $mockCoverageFileRetrievalService->expects($this->never())
            ->method('deleteFromS3');

        $mockCoverageFilePersistService = $this->createMock(CoverageFilePersistService::class);
        $mockCoverageFilePersistService->expects($this->never())
            ->method('persist');

        $mockEventBridgeEventService = $this->createMock(EventBridgeEventClient::class);
        $mockEventBridgeEventService->expects($this->never())
            ->method('publishEvent');

        $handler = new EventHandler(
            $this->getContainer()->get(SerializerInterface::class),
            $mockCoverageFileRetrievalService,
            $this->getContainer()->get(CoverageFileParserService::class),
            $mockCoverageFilePersistService,
            $mockEventBridgeEventService,
            new NullLogger()
        );

        $handler->handleS3($event, Context::fake());
    }

    #[DataProvider('validS3EventDataProvider')]
    public function testHandleS3FailsToPersist(S3Event $event, array $coverageFiles, array $expectedOutputKeys): void
    {
        $mockCoverageFileRetrievalService = $this->createMock(CoverageFileRetrievalService::class);
        $mockCoverageFileRetrievalService->method('ingestFromS3')
            ->willReturnOnConsecutiveCalls(
                ...array_map(
                    [$this, 'getMockS3ObjectResponse'],
                    $coverageFiles
                )
            );

        // The handler never attempts to delete the ingested file unless _everything_ succeeds
        // (including persisting the upload to all destinations)
        $mockCoverageFileRetrievalService->expects($this->never())
            ->method('deleteFromS3');

        $mockCoverageFilePersistService = $this->createMock(CoverageFilePersistService::class);
        $mockCoverageFilePersistService->method('persist')
            ->willThrowException(
                PersistException::from(new Exception('Failed to persist'))
            );

        $mockEventBridgeEventService = $this->createMock(EventBridgeEventClient::class);
        $mockEventBridgeEventService
            ->expects($this->exactly(count($coverageFiles)))
            ->method('publishEvent')
            ->with(CoverageEvent::INGEST_FAILURE);

        $handler = new EventHandler(
            $this->getContainer()->get(SerializerInterface::class),
            $mockCoverageFileRetrievalService,
            $this->getContainer()->get(CoverageFileParserService::class),
            $mockCoverageFilePersistService,
            $mockEventBridgeEventService,
            new NullLogger()
        );

        $handler->handleS3($event, Context::fake());
    }

    #[DataProvider('validS3EventDataProvider')]
    public function testHandleS3FailsToDelete(S3Event $event, array $coverageFiles, array $expectedOutputKeys): void
    {
        $mockCoverageFileRetrievalService = $this->createMock(CoverageFileRetrievalService::class);
        $mockCoverageFileRetrievalService->expects($this->exactly(count($event->getRecords())))
            ->method('ingestFromS3')
            ->willReturnOnConsecutiveCalls(
                ...array_map(
                    [$this, 'getMockS3ObjectResponse'],
                    $coverageFiles
                )
            );

        $mockCoverageFileRetrievalService->expects($this->exactly(count($expectedOutputKeys)))
            ->method('deleteFromS3')
            ->willThrowException(DeletionException::from(new Exception('Failed to delete')));

        $mockCoverageFilePersistService = $this->createMock(CoverageFilePersistService::class);
        $mockCoverageFilePersistService->expects($this->exactly(count($expectedOutputKeys)))
            ->method('persist')
            ->with(
                self::callback(
                    static fn(Upload $upload) => $upload->getuploadId() === 'mock-uuid' &&
                        $upload->getCommit() === '1' &&
                        $upload->getParent() === ['2']
                ),
            )
            ->willReturn(true);

        $mockEventBridgeEventService = $this->createMock(EventBridgeEventClient::class);
        $mockEventBridgeEventService->expects($this->exactly(count($coverageFiles) - count($expectedOutputKeys)))
            ->method('publishEvent');

        $handler = new EventHandler(
            $this->getContainer()->get(SerializerInterface::class),
            $mockCoverageFileRetrievalService,
            $this->getContainer()->get(CoverageFileParserService::class),
            $mockCoverageFilePersistService,
            $mockEventBridgeEventService,
            new NullLogger()
        );

        $handler->handleS3($event, Context::fake());
    }

    private function getMockS3ObjectResponse(string $body): GetObjectOutput|MockObject
    {
        $mockStream = $this->createMock(ResultStream::class);
        $mockStream->method('getContentAsString')
            ->willReturn($body);

        $mockResponse = $this->createMock(GetObjectOutput::class);
        $mockResponse->method('getBody')
            ->willReturn($mockStream);
        $mockResponse->method('getMetadata')
            ->willReturn([
                'uploadId' => 'mock-uuid',
                'provider' => Provider::GITHUB->value,
                'projectRoot' => 'mock/project/root/',
                'commit' => '1',
                'parent' => '["2"]',
                'pullRequest' => 1234,
                'tag' => 'frontend',
                'owner' => 'ryanmab',
                'repository' => 'portfolio',
                'ref' => 'mock-branch-reference',
            ]);

        return $mockResponse;
    }

    /**
     * @throws InvalidLambdaEvent
     */
    public static function validS3EventDataProvider(): array
    {
        return [
            'Single valid file (Lcov)' => [
                new S3Event([
                    'Records' => [
                        [
                            'eventSource' => 'aws:s3',
                            'eventTime' => '2023-05-02 12:00:00',
                            's3' => [
                                'bucket' => [
                                    'name' => 'mock-bucket',
                                    'arn' => 'mock-arn'
                                ],
                                'object' => [
                                    'key' => 'some-path/lcov.info'
                                ]
                            ]
                        ]
                    ]
                ]),
                [
                    file_get_contents(__DIR__ . '/../Fixture/Lcov/complex.info'),
                ],
                [
                    'some-path/mock-uuid.json'
                ]
            ],
            'Single valid file (Clover)' => [
                new S3Event([
                    'Records' => [
                        [
                            'eventSource' => 'aws:s3',
                            'eventTime' => '2023-05-02 12:00:00',
                            's3' => [
                                'bucket' => [
                                    'name' => 'mock-bucket',
                                    'arn' => 'mock-arn'
                                ],
                                'object' => [
                                    'key' => 'clover.xml'
                                ]
                            ]
                        ]
                    ]
                ]),
                [
                    file_get_contents(__DIR__ . '/../Fixture/Clover/complex-jest.xml'),
                ],
                [
                    'mock-uuid.json'
                ]
            ],
            'Multiple valid files (Lcov and Clover)' => [
                new S3Event([
                    'Records' => [
                        [
                            'eventSource' => 'aws:s3',
                            'eventTime' => '2023-05-02 12:00:00',
                            's3' => [
                                'bucket' => [
                                    'name' => 'mock-bucket',
                                    'arn' => 'mock-arn'
                                ],
                                'object' => [
                                    'key' => 'clover.xml'
                                ]
                            ]
                        ],
                        [
                            'eventSource' => 'aws:s3',
                            'eventTime' => '2023-05-02 12:00:00',
                            's3' => [
                                'bucket' => [
                                    'name' => 'mock-bucket',
                                    'arn' => 'mock-arn'
                                ],
                                'object' => [
                                    'key' => 'much/longer/nested/path/different-name.xml'
                                ]
                            ]
                        ]
                    ]
                ]),
                [
                    file_get_contents(__DIR__ . '/../Fixture/Clover/complex-php.xml'),
                    file_get_contents(__DIR__ . '/../Fixture/Clover/complex-jest.xml')
                ],
                [
                    'mock-uuid.json',
                    'much/longer/nested/path/mock-uuid.json'
                ]
            ],
            'Valid and invalid files (Lcov)' => [
                new S3Event([
                    'Records' => [
                        [
                            'eventSource' => 'aws:s3',
                            'eventTime' => '2023-05-02 12:00:00',
                            's3' => [
                                'bucket' => [
                                    'name' => 'mock-bucket',
                                    'arn' => 'mock-arn'
                                ],
                                'object' => [
                                    'key' => 'some-invalid-file.xml'
                                ]
                            ]
                        ],
                        [
                            'eventSource' => 'aws:s3',
                            'eventTime' => '2023-05-02 12:00:00',
                            's3' => [
                                'bucket' => [
                                    'name' => 'mock-bucket',
                                    'arn' => 'mock-arn'
                                ],
                                'object' => [
                                    'key' => 'totally-valid-file.info'
                                ]
                            ]
                        ]
                    ]
                ]),
                [
                    'mock-invalid-file',
                    file_get_contents(__DIR__ . '/../Fixture/Lcov/complex.info')
                ],
                [
                    'mock-uuid.json'
                ]
            ],
            'Empty files (Lcov and Clover)' => [
                new S3Event([
                    'Records' => [
                        [
                            'eventSource' => 'aws:s3',
                            'eventTime' => '2023-05-02 12:00:00',
                            's3' => [
                                'bucket' => [
                                    'name' => 'mock-bucket',
                                    'arn' => 'mock-arn'
                                ],
                                'object' => [
                                    'key' => 'some-path/lcov/empty.info'
                                ]
                            ]
                        ],
                        [
                            'eventSource' => 'aws:s3',
                            'eventTime' => '2023-05-02 12:00:00',
                            's3' => [
                                'bucket' => [
                                    'name' => 'mock-bucket',
                                    'arn' => 'mock-arn'
                                ],
                                'object' => [
                                    'key' => 'some-path/clover/empty-jest.xml'
                                ]
                            ]
                        ]
                    ]
                ]),
                [
                    file_get_contents(__DIR__ . '/../Fixture/Lcov/empty.info'),
                    file_get_contents(__DIR__ . '/../Fixture/Clover/empty-jest.xml'),
                ],
                [
                    'some-path/lcov/mock-uuid.json',
                    'some-path/clover/mock-uuid.json'
                ]
            ],
        ];
    }
}
