<?php

namespace App\Tests\Handler;

use App\Client\BigQueryClient;
use App\Handler\IngestHandler;
use App\Model\Upload;
use App\Service\CoverageFileParserService;
use App\Service\CoverageFilePersistService;
use App\Service\CoverageFileRetrievalService;
use App\Service\Persist\BigQueryPersistService;
use App\Service\UniqueIdGeneratorService;
use App\Strategy\Clover\CloverParseStrategy;
use App\Strategy\Lcov\LcovParseStrategy;
use AsyncAws\Core\Stream\ResultStream;
use AsyncAws\S3\Result\GetObjectOutput;
use Bref\Context\Context;
use Bref\Event\InvalidLambdaEvent;
use Bref\Event\S3\S3Event;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class IngestHandlerTest extends TestCase
{
    /**
     * @throws Exception
     * @throws InvalidLambdaEvent
     */
    #[DataProvider('validS3EventDataProvider')]
    public function testHandleS3(S3Event $event, array $coverageFiles, array $expectedOutputKeys): void
    {
        $mockUniqueIdGenerator = $this->createMock(UniqueIdGeneratorService::class);
        $mockUniqueIdGenerator->expects($this->exactly(count($coverageFiles)))
            ->method('generate')
            ->willReturn('mock-uuid');

        $mockCoverageFileRetrievalService = $this->createMock(CoverageFileRetrievalService::class);
        $mockCoverageFileRetrievalService->expects($this->exactly(count($event->getRecords())))
            ->method('ingestFromS3')
            ->willReturnOnConsecutiveCalls(
                ...array_map(
                    [$this, "getMockS3ObjectResponse"],
                    $coverageFiles
                )
            );

        $mockCoverageFilePersistService = $this->createMock(CoverageFilePersistService::class);
        $mockCoverageFilePersistService->expects($this->exactly(count($expectedOutputKeys)))
            ->method('persist')
            ->with(
                self::callback(
                    static fn(Upload $upload) => $upload->getUploadId() === 'mock-uuid' &&
                        $upload->getCommit() === '1' &&
                        $upload->getParent() === '2'
                ),
            );

        $handler = new IngestHandler(
            $mockCoverageFileRetrievalService,
            $this->getRealCoverageFileParserService(),
            $mockCoverageFilePersistService,
            $mockUniqueIdGenerator,
            new NullLogger()
        );

        $handler->handleS3($event, Context::fake());
    }

    private function getRealCoverageFileParserService(): CoverageFileParserService
    {
        return new CoverageFileParserService(
            [
                new LcovParseStrategy(new NullLogger()),
                new CloverParseStrategy(new NullLogger())
            ],
            new NullLogger()
        );
    }

    private function getMockS3ObjectResponse(string $body): GetObjectOutput|MockObject
    {
        $mockStream = $this->createMock(ResultStream::class);
        $mockStream->method("getContentAsString")
            ->willReturn($body);

        $mockResponse = $this->createMock(GetObjectOutput::class);
        $mockResponse->method("getBody")
            ->willReturn($mockStream);
        $mockResponse->method("getMetadata")
            ->willReturn([
                "commit" => "6fc03961c51e4b5fb91f423ebdfd830b5fd11ed4",
                "parent" => "2",
                "pullRequest" => "1242",
                "owner" => "ryanmab",
                "repository" => "portfolio"
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
            ]
        ];
    }

    #[DataProvider('anotherDataProvider')]
    public function testHandleS3Again(string $coverageFile): void
    {
        $mockCoverageFileRetrievalService = $this->createMock(CoverageFileRetrievalService::class);
        $mockCoverageFileRetrievalService->expects($this->once())
            ->method('ingestFromS3')
            ->willReturn($this->getMockS3ObjectResponse($coverageFile));

        $handler = new IngestHandler(
            $mockCoverageFileRetrievalService,
            $this->getRealCoverageFileParserService(),
            new CoverageFilePersistService([
                new BigQueryPersistService(
                    new BigQueryClient(),
                    new NullLogger()
                )
            ], new NullLogger()),
            new UniqueIdGeneratorService(),
            new NullLogger()
        );

        $handler->handleS3(
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
            Context::fake()
        );
    }

    public static function anotherDataProvider(): array
    {
        return [
            [
                file_get_contents(__DIR__ . '/backend.xml'),
            ],
            [
                file_get_contents(__DIR__ . '/frontend.info'),
            ],
            [
                file_get_contents(__DIR__ . '/storybook.info'),
            ]
        ];
    }
}
