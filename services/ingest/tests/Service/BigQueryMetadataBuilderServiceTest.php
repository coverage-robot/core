<?php

namespace App\Tests\Service;

use App\Service\BigQueryMetadataBuilderService;
use Packages\Models\Enum\LineType;
use Packages\Models\Model\Line\AbstractLine;
use Packages\Models\Model\Line\Branch;
use Packages\Models\Model\Line\Method;
use Packages\Models\Model\Line\Statement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class BigQueryMetadataBuilderServiceTest extends TestCase
{
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
