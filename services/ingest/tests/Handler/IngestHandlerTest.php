<?php

namespace App\Tests\Handler;

use App\Enum\EnvironmentEnum;
use App\Handler\IngestHandler;
use App\Model\ProjectCoverage;
use App\Service\CoverageFileParserService;
use App\Service\CoverageFilePersistService;
use App\Service\CoverageFileRetrievalService;
use App\Service\UniqueIdGeneratorService;
use App\Strategy\Clover\CloverParseStrategy;
use App\Strategy\Lcov\LcovParseStrategy;
use App\Tests\Mock\Factory\MockEnvironmentServiceFactory;
use Bref\Context\Context;
use Bref\Event\InvalidLambdaEvent;
use Bref\Event\S3\S3Event;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

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
            ->method("generate")
            ->willReturn("mock-uuid");

        $mockCoverageFileRetrievalService = $this->createMock(CoverageFileRetrievalService::class);
        $mockCoverageFileRetrievalService->expects($this->exactly(count($event->getRecords())))
            ->method('ingestFromS3')
            ->willReturnOnConsecutiveCalls(...$coverageFiles);

        $mockCoverageFilePersistService = $this->createMock(CoverageFilePersistService::class);
        $mockCoverageFilePersistService->expects($this->exactly(count($expectedOutputKeys)))
            ->method('persistToS3')
            ->with(
                'coverage-output-dev',
                self::callback(static fn(string $outputKey) => in_array($outputKey, $expectedOutputKeys)),
                self::callback(static fn(ProjectCoverage $coverage) => !!$coverage->jsonSerialize())
            );

        $handler = new IngestHandler(
            MockEnvironmentServiceFactory::getMock($this, EnvironmentEnum::DEVELOPMENT),
            $mockCoverageFileRetrievalService,
            $this->getRealCoverageFileParserService(),
            $mockCoverageFilePersistService,
            $mockUniqueIdGenerator
        );

        $handler->handleS3($event, Context::fake());
    }

    private function getRealCoverageFileParserService(): CoverageFileParserService
    {
        return new CoverageFileParserService([
            new LcovParseStrategy(),
            new CloverParseStrategy()
        ]);
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
}
