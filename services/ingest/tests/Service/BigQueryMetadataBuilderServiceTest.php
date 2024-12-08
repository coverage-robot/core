<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Model\Coverage;
use App\Model\File;
use App\Model\Line\AbstractLine;
use App\Model\Line\Branch;
use App\Model\Line\Method;
use App\Model\Line\Statement;
use App\Service\BigQueryMetadataBuilderService;
use App\Tests\Mock\Factory\MockNormalizerFactory;
use DateTimeImmutable;
use Iterator;
use Packages\Contracts\Format\CoverageFormat;
use Packages\Contracts\Line\LineType;
use Packages\Contracts\Provider\Provider;
use Packages\Contracts\Tag\Tag;
use Packages\Event\Model\Upload;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

final class BigQueryMetadataBuilderServiceTest extends KernelTestCase
{
    public function testBuildRow(): void
    {
        $bigQueryMetadataBuilderService = new BigQueryMetadataBuilderService(
            new NullLogger(),
            $this->getContainer()->get(SerializerInterface::class)
        );

        $ingestTime = new DateTimeImmutable();

        $row = $bigQueryMetadataBuilderService->buildLineCoverageRow(
            new Upload(
                uploadId: 'mock-uuid',
                provider: Provider::GITHUB,
                projectId: '0192c0b2-a63e-7c29-8636-beb65b9097ee',
                owner: 'mock-owner',
                repository: 'mock-repository',
                commit: 'mock-commit',
                parent: [],
                ref: 'main',
                projectRoot: 'project/root',
                tag: new Tag('mock-tag', 'mock-commit', [0]),
                pullRequest: 1234,
                baseCommit: 'commit-on-main',
                baseRef: 'main',
                eventTime: $ingestTime
            ),
            1,
            new Coverage(
                sourceFormat: CoverageFormat::CLOVER,
                root: 'path/from/root',
                generatedAt: $ingestTime
            ),
            new File('mock-file.ts'),
            new Statement(
                lineNumber: 1,
                lineHits: 10
            )
        );

        $this->assertEquals(
            [
                'uploadId' => 'mock-uuid',
                'ingestTime' => $ingestTime->format('Y-m-d H:i:s'),
                'provider' => Provider::GITHUB,
                'owner' => 'mock-owner',
                'repository' => 'mock-repository',
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
        $bigQueryMetadataBuilderService = new BigQueryMetadataBuilderService(
            new NullLogger(),
            $this->getContainer()->get(SerializerInterface::class)
        );

        $metadata = $bigQueryMetadataBuilderService->buildMetadata($line);

        $this->assertEquals($expectedMetadata, $metadata);
    }

    public static function lineDataProvider(): Iterator
    {
        yield LineType::BRANCH->value => [
            new Branch(
                lineNumber: 1,
                lineHits: 1,
                branchHits: [0 => 0, 1 => 1]
            ),
            [
                [
                    'key' => 'branchHits',
                    'value' => '[0,1]'
                ],
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
            ]
        ];

        yield LineType::STATEMENT->value => [
            new Statement(
                lineNumber: 1,
                lineHits: 10
            ),
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
        ];

        yield LineType::METHOD->value => [
            new Method(
                lineNumber: 1,
                lineHits: 10,
                name: 'some-method'
            ),
            [
                [
                    'key' => 'name',
                    'value' => 'some-method'
                ],
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
            ]
        ];
    }
}
