<?php

namespace App\Tests\Service\Persist;

use App\Client\BigQueryClient;
use App\Enum\EnvironmentVariable;
use App\Service\BigQueryMetadataBuilderService;
use App\Service\Persist\BigQueryPersistService;
use App\Tests\Mock\Factory\MockEnvironmentServiceFactory;
use Google\Cloud\BigQuery\Dataset;
use Google\Cloud\BigQuery\InsertResponse;
use Google\Cloud\BigQuery\Table;
use Packages\Models\Enum\CoverageFormat;
use Packages\Models\Enum\Environment;
use Packages\Models\Enum\LineType;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Coverage;
use Packages\Models\Model\File;
use Packages\Models\Model\Line\Branch;
use Packages\Models\Model\Line\Method;
use Packages\Models\Model\Line\Statement;
use Packages\Models\Model\Tag;
use Packages\Models\Model\Upload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;

class BigQueryPersistServiceTest extends TestCase
{
    #[DataProvider('coverageDataProvider')]
    public function testPersistWithVaryingChunks(
        Upload $upload,
        Coverage $coverage,
        int $chunkSize,
        array $expectedInsertedChunks
    ): void {
        $insertResponse = $this->createMock(InsertResponse::class);
        $insertResponse->method('isSuccessful')
            ->willReturn(true);
        $insertResponse->method('failedRows')
            ->willReturn([]);

        $mockTable = $this->createMock(Table::class);
        $insertMatcher = $this->exactly(count($expectedInsertedChunks));
        $mockTable->expects($insertMatcher)
            ->method('insertRows')
            ->with(
                self::callback(
                    static fn(array $rows) => $rows == $expectedInsertedChunks[$insertMatcher->numberOfInvocations(
                    ) - 1]
                )
            )
            ->willReturn($insertResponse);

        $mockBigQueryDataset = $this->createMock(Dataset::class);
        $mockBigQueryDataset->expects($this->once())
            ->method('table')
            ->with('mock-table')
            ->willReturn($mockTable);

        $mockBigQueryClient = $this->createMock(BigQueryClient::class);
        $mockBigQueryClient->expects($this->once())
            ->method('getEnvironmentDataset')
            ->willReturn($mockBigQueryDataset);

        $bigQueryPersistService = new BigQueryPersistService(
            $mockBigQueryClient,
            new BigQueryMetadataBuilderService(new NullLogger()),
            MockEnvironmentServiceFactory::getMock(
                $this,
                Environment::TESTING,
                [
                    EnvironmentVariable::BIGQUERY_LINE_COVERAGE_TABLE->value => 'mock-table'
                ]
            ),
            new NullLogger(),
            $chunkSize
        );

        $this->assertTrue($bigQueryPersistService->persist($upload, $coverage));
    }

    #[DataProvider('coverageDataProvider')]
    public function testPersistingAPartialFailure(
        Upload $upload,
        Coverage $coverage,
        int $chunkSize,
        array $expectedInsertedChunks
    ): void {
        $insertResponse = $this->createMock(InsertResponse::class);
        $insertResponse->method('isSuccessful')
            ->willReturn(false);
        $insertResponse->method('failedRows')
            ->willReturn([]);

        $mockTable = $this->createMock(Table::class);
        $insertMatcher = $this->exactly(count($expectedInsertedChunks));
        $mockTable->expects($insertMatcher)
            ->method('insertRows')
            ->with(
                self::callback(
                    static fn(array $rows) => $rows == $expectedInsertedChunks[$insertMatcher->numberOfInvocations(
                    ) - 1]
                )
            )
            ->willReturn($insertResponse);

        $mockBigQueryDataset = $this->createMock(Dataset::class);
        $mockBigQueryDataset->expects($this->once())
            ->method('table')
            ->with('mock-table')
            ->willReturn($mockTable);

        $mockBigQueryClient = $this->createMock(BigQueryClient::class);
        $mockBigQueryClient->expects($this->once())
            ->method('getEnvironmentDataset')
            ->willReturn($mockBigQueryDataset);

        $bigQueryPersistService = new BigQueryPersistService(
            $mockBigQueryClient,
            new BigQueryMetadataBuilderService(new NullLogger()),
            MockEnvironmentServiceFactory::getMock(
                $this,
                Environment::TESTING,
                [
                    EnvironmentVariable::BIGQUERY_LINE_COVERAGE_TABLE->value => 'mock-table'
                ]
            ),
            new NullLogger(),
            $chunkSize
        );

        $this->assertFalse($bigQueryPersistService->persist($upload, $coverage));
    }

    #[DataProvider('coverageDataProvider')]
    public function testPersistingWithAnEmptyFileAtEnd(
        Upload $upload,
        Coverage $coverage,
        int $chunkSize,
        array $expectedInsertedChunks
    ): void {
        // Add a file to the end, with no lines
        $coverage->addFile(new File('file-with-no-lines'));

        $insertResponse = $this->createMock(InsertResponse::class);
        $insertResponse->method('isSuccessful')
            ->willReturn(true);
        $insertResponse->method('failedRows')
            ->willReturn([]);

        $mockTable = $this->createMock(Table::class);
        $insertMatcher = $this->exactly(count($expectedInsertedChunks));
        $mockTable->expects($insertMatcher)
            ->method('insertRows')
            ->with(
                self::callback(
                    static fn(array $rows) => $rows == $expectedInsertedChunks[$insertMatcher->numberOfInvocations(
                    ) - 1]
                )
            )
            ->willReturn($insertResponse);

        $mockBigQueryDataset = $this->createMock(Dataset::class);
        $mockBigQueryDataset->expects($this->once())
            ->method('table')
            ->with('mock-table')
            ->willReturn($mockTable);

        $mockBigQueryClient = $this->createMock(BigQueryClient::class);
        $mockBigQueryClient->expects($this->once())
            ->method('getEnvironmentDataset')
            ->willReturn($mockBigQueryDataset);

        $bigQueryPersistService = new BigQueryPersistService(
            $mockBigQueryClient,
            new BigQueryMetadataBuilderService(new NullLogger()),
            MockEnvironmentServiceFactory::getMock(
                $this,
                Environment::TESTING,
                [
                    EnvironmentVariable::BIGQUERY_LINE_COVERAGE_TABLE->value => 'mock-table'
                ]
            ),
            new NullLogger(),
            $chunkSize
        );

        $this->assertTrue($bigQueryPersistService->persist($upload, $coverage));
    }

    public static function coverageDataProvider(): iterable
    {
        $chunkSize = 6;

        $upload = new Upload(
            Uuid::uuid4()->toString(),
            Provider::GITHUB,
            '',
            '',
            '',
            [],
            'mock-branch-reference',
            1,
            new Tag('mock-tag', '')
        );

        for ($numberOfLines = 6; $numberOfLines <= 10; $numberOfLines++) {
            $coverage = new Coverage(CoverageFormat::LCOV, 'mock/project/root');
            $expectedInsertedRows = [];

            for ($numberOfFiles = 1; $numberOfFiles <= 3; $numberOfFiles++) {
                // Clone the coverage object so that we can add an additional file per yield, while
                // modifying the original object
                $coverage = clone $coverage;

                $file = new File('mock-file-' . $numberOfFiles);

                for ($i = 1; $i <= $numberOfLines; $i++) {
                    $line = match ($i % 3) {
                        0 => new Branch($i, $i % 2, [0 => 0, 1 => 2, 3 => 0]),
                        1 => new Statement($i, $i % 2),
                        2 => new Method($i, $i % 2, 'mock-method-' . $i)
                    };
                    $file->setLine($line);

                    $commonColumns = [
                        'uploadId' => $upload->getUploadId(),
                        'ingestTime' => $upload->getIngestTime()->format('Y-m-d H:i:s'),
                        'provider' => $upload->getProvider()->value,
                        'owner' => $upload->getOwner(),
                        'repository' => $upload->getRepository(),
                        'commit' => $upload->getCommit(),
                        'parent' => $upload->getParent(),
                        'ref' => $upload->getRef(),
                        'tag' => $upload->getTag()->getName(),
                        'sourceFormat' => CoverageFormat::LCOV,
                        'fileName' => $file->getFileName(),
                        'generatedAt' => $coverage->getGeneratedAt(),
                        'type' => $line->getType(),
                        'lineNumber' => $line->getLineNumber(),
                    ];

                    $commonMetadata = [
                        [
                            'key' => 'type',
                            'value' => $line->getType()->value,
                        ],
                        [
                            'key' => 'lineNumber',
                            'value' => $line->getLineNumber()
                        ],
                        [
                            'key' => 'lineHits',
                            'value' => $line->getLineHits()
                        ]
                    ];

                    $expectedInsertedRows[] = [
                        'data' => [
                            ...match ($line->getType()) {
                                LineType::STATEMENT => $commonColumns + [
                                        'metadata' => $commonMetadata,
                                    ],
                                LineType::BRANCH => $commonColumns + [
                                        'metadata' => array_merge(
                                            $commonMetadata,
                                            [
                                                [
                                                    'key' => 'branchHits',
                                                    'value' => '{"0":0,"1":2,"3":0}'
                                                ]
                                            ]
                                        )
                                    ],
                                LineType::METHOD => $commonColumns + [
                                        'metadata' => array_merge(
                                            $commonMetadata,
                                            [
                                                [
                                                    'key' => 'name',
                                                    'value' => $line->getName()
                                                ]
                                            ]
                                        )
                                    ],
                            }
                        ]
                    ];
                }

                $coverage->addFile($file);

                yield sprintf('%s files with %s line(s) each', $numberOfFiles, $numberOfLines) => [
                    $upload,
                    $coverage,
                    $chunkSize,
                    array_chunk($expectedInsertedRows, $chunkSize)
                ];
            }
        }
    }
}
