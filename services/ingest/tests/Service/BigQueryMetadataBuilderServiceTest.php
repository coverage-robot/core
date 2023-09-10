<?php

namespace App\Tests\Service;

use App\Service\BigQueryMetadataBuilderService;
use DateTimeImmutable;
use Packages\Models\Enum\CoverageFormat;
use Packages\Models\Enum\LineType;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Coverage;
use Packages\Models\Model\File;
use Packages\Models\Model\Line\AbstractLine;
use Packages\Models\Model\Line\Branch;
use Packages\Models\Model\Line\Method;
use Packages\Models\Model\Line\Statement;
use Packages\Models\Model\Tag;
use Packages\Models\Model\Event\Upload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class BigQueryMetadataBuilderServiceTest extends TestCase
{
    public function testBuildRow(): void
    {
        $bigQueryMetadataBuilderService = new BigQueryMetadataBuilderService(new NullLogger());

        $ingestTime = new DateTimeImmutable();

        $row = $bigQueryMetadataBuilderService->buildRow(
            new Upload(
                'mock-uuid',
                Provider::GITHUB,
                'mock-repository',
                'mock-branch',
                'mock-commit',
                [],
                'main',
                1234,
                new Tag('mock-tag', 'mock-commit'),
                $ingestTime
            ),
            1,
            new Coverage(CoverageFormat::CLOVER, 'path/from/root', $ingestTime),
            new File('mock-file.ts', []),
            new Statement(1, 10)
        );

        $this->assertEquals(
            [
                'uploadId' => 'mock-uuid',
                'ingestTime' => $ingestTime->format('Y-m-d H:i:s'),
                'provider' => 'github',
                'owner' => 'mock-repository',
                'repository' => 'mock-branch',
                'commit' => 'mock-commit',
                'parent' => [],
                'ref' => 'main',
                'tag' => 'mock-tag',
                'sourceFormat' => CoverageFormat::CLOVER,
                'totalLines' => 1,
                'fileName' => 'mock-file.ts',
                'generatedAt' => $ingestTime->format('Y-m-d H:i:s'),
                'type' => LineType::STATEMENT,
                'lineNumber' => 1,
                'metadata' => [
                    [
                        'key' => 'type',
                        'value' => 'STATEMENT'
                    ],
                    [
                        'key' => 'lineNumber',
                        'value' => '1',
                    ],
                    [
                        'key' => 'lineHits',
                        'value' => '10',
                    ]
                ]
            ],
            $row
        );
    }

    #[DataProvider('lineDataProvider')]
    public function testBuildMetadata(AbstractLine $line, array $expectedMetadata): void
    {
        $bigQueryMetadataBuilderService = new BigQueryMetadataBuilderService(new NullLogger());

        $metadata = $bigQueryMetadataBuilderService->buildMetadata($line);

        $this->assertEquals($expectedMetadata, $metadata);
    }

    public static function lineDataProvider(): array
    {
        return [
            LineType::BRANCH->value => [
                new Branch(1, 1, [0 => 0, 1 => 1]),
                [
                    [
                        'key' => 'type',
                        'value' => LineType::BRANCH->value
                    ],
                    [
                        'key' => 'lineNumber',
                        'value' => 1
                    ],
                    [
                        'key' => 'lineHits',
                        'value' => 1
                    ],
                    [
                        'key' => 'branchHits',
                        'value' => '[0,1]'
                    ]
                ]
            ],
            LineType::STATEMENT->value => [
                new Statement(1, 10),
                [
                    [
                        'key' => 'type',
                        'value' => LineType::STATEMENT->value
                    ],
                    [
                        'key' => 'lineNumber',
                        'value' => 1
                    ],
                    [
                        'key' => 'lineHits',
                        'value' => 10
                    ]
                ]
            ],
            LineType::METHOD->value => [
                new Method(1, 10, 'some-method'),
                [
                    [
                        'key' => 'type',
                        'value' => LineType::METHOD->value
                    ],
                    [
                        'key' => 'lineNumber',
                        'value' => 1
                    ],
                    [
                        'key' => 'lineHits',
                        'value' => 10
                    ],
                    [
                        'key' => 'name',
                        'value' => 'some-method'
                    ]
                ]
            ],
        ];
    }
}
