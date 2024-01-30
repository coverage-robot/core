<?php

namespace Packages\Telemetry\Tests\Service;

use DateTimeImmutable;
use EnricoStahn\JsonAssert\Assert;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Telemetry\Enum\EnvironmentVariable;
use Packages\Telemetry\Enum\Resolution;
use Packages\Telemetry\Enum\Unit;
use Packages\Telemetry\Service\MetricService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\UidNormalizer;
use Symfony\Component\Serializer\Serializer;

final class MetricServiceTest extends TestCase
{
    use Assert;

    /**
     * @param (float|int)[]|float|int $value
     * @param string[][] $dimensions
     * @param array<string, (float|int)[]|float|int> $properties
     *
     * @throws Exception
     */
    #[DataProvider('putMetricDataProvider')]
    public function testMetricBuilding(
        string $metric,
        array|float|int $value,
        Unit $unit,
        Resolution $resolution,
        array $dimensions,
        array $properties
    ): void {
        $mockMetricsLogger = $this->createMock(LoggerInterface::class);
        $mockClock = $this->createMock(ClockInterface::class);
        $mockClock->method('now')
            ->willReturn(new DateTimeImmutable('2023-11-20 09:00:00'));

        $mockEnvironmentService = $this->createMock(EnvironmentServiceInterface::class);
        $mockEnvironmentService->method('getVariable')
            ->willReturnMap(
                [
                    [
                        EnvironmentVariable::AWS_LAMBDA_FUNCTION_NAME,
                        'mock-function-name'
                    ],
                    [
                        EnvironmentVariable::AWS_LAMBDA_FUNCTION_VERSION,
                        '$LATEST'
                    ]
                ]
            );

        $metricService = new MetricService(
            $mockMetricsLogger,
            $mockClock,
            $mockEnvironmentService,
            new Serializer(
                [
                    new ArrayDenormalizer(),
                    new UidNormalizer(),
                    new BackedEnumNormalizer(),
                    new DateTimeNormalizer(),
                    new ObjectNormalizer(
                        classMetadataFactory: new ClassMetadataFactory(
                            new AttributeLoader()
                        ),
                        nameConverter: new MetadataAwareNameConverter(
                            new ClassMetadataFactory(
                                new AttributeLoader()
                            ),
                            new CamelCaseToSnakeCaseNameConverter()
                        ),
                    ),
                ],
                [new JsonEncoder()]
            )
        );

        $mockMetricsLogger->expects($this->once())
            ->method('info')
            ->with(
                self::callback(function (string $serialisedMetric): bool {
                    $json = json_decode($serialisedMetric);

                    $this->assertJsonMatchesSchema(
                        $json,
                        './tests/Service/cloudwatch-emf-schema.json'
                    );
                    $this->assertJsonValueEquals(
                        'mock-function-name',
                        MetricService::FUNCTION_NAME,
                        $json
                    );
                    $this->assertJsonValueEquals(
                        '$LATEST',
                        MetricService::FUNCTION_VERSION,
                        $json
                    );

                    return true;
                })
            );

        $metricService->put(
            $metric,
            $value,
            $unit,
            $resolution,
            $dimensions,
            $properties
        );
    }

    public static function putMetricDataProvider(): array
    {
        return [
            [
                'mock-metric',
                1,
                Unit::COUNT,
                Resolution::LOW,
                [],
                []
            ],
            [
                'mock-metric-2',
                1000,
                Unit::MILLISECONDS,
                Resolution::HIGH,
                [
                    ['owner'],
                    ['owner', 'repository']
                ],
                [
                    'owner' => 1,
                    'repository' => 2
                ]
            ]
        ];
    }
}
